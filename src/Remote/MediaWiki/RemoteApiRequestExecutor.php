<?php

namespace FileImporter\Remote\MediaWiki;

use CentralIdLookup;
use Exception;
use FileImporter\Data\SourceUrl;
use FileImporter\Services\Http\HttpRequestExecutor;
use MediaWiki\User\UserIdentity;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use User;

/**
 * Use CentralAuth to execute API calls on a sibling wiki.
 *
 * @license GPL-2.0-or-later
 */
class RemoteApiRequestExecutor implements LoggerAwareInterface {

	/**
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * @var HttpApiLookup
	 */
	private $httpApiLookup;

	/**
	 * @var HttpRequestExecutor
	 */
	private $httpRequestExecutor;

	/**
	 * @var CentralAuthTokenProvider
	 */
	private $centralAuthTokenProvider;

	/**
	 * @var CentralIdLookup
	 */
	private $centralIdLookup;

	/**
	 * @param HttpApiLookup $httpApiLookup
	 * @param HttpRequestExecutor $httpRequestExecutor
	 * @param CentralAuthTokenProvider $centralAuthTokenProvider
	 * @param CentralIdLookup $centralIdLookup
	 */
	public function __construct(
		HttpApiLookup $httpApiLookup,
		HttpRequestExecutor $httpRequestExecutor,
		CentralAuthTokenProvider $centralAuthTokenProvider,
		CentralIdLookup $centralIdLookup
	) {
		$this->httpApiLookup = $httpApiLookup;
		$this->httpRequestExecutor = $httpRequestExecutor;
		$this->centralAuthTokenProvider = $centralAuthTokenProvider;
		$this->centralIdLookup = $centralIdLookup;
		$this->logger = new NullLogger();
	}

	/**
	 * @param LoggerInterface $logger
	 * @codeCoverageIgnore
	 */
	public function setLogger( LoggerInterface $logger ) {
		$this->logger = $logger;
	}

	/**
	 * @param SourceUrl $sourceUrl
	 * @param User $user
	 * @param array $params API request params
	 * @param bool $usePost
	 * @return array|null Null in case of an error. Calling code can't understand why, but the error
	 *  is logged.
	 */
	public function execute(
		SourceUrl $sourceUrl,
		User $user,
		array $params,
		bool $usePost = false
	): ?array {
		// TODO handle error
		if ( !$this->canUseCentralAuth( $user ) ) {
			$this->logger->error( __METHOD__ . ' user can\'t use CentralAuth.' );
			return null;
		}

		$result = $this->doRequest( $sourceUrl, $user, $params, $usePost );

		// It's an array of "errors" with errorformat=plaintext, but a single "error" without.
		// Each error contains "code" and "info" with formatversion=2, but "code" and "*" without.
		if ( isset( $result['errors'] ) || isset( $result['error'] ) ) {
			$this->logger->error( 'Remote API responded with an error', [
				'sourceUrl' => $sourceUrl->getUrl(),
				'apiParameters' => $params,
				'response' => $result,
			] );
		}

		return $result;
	}

	/**
	 * @param UserIdentity $user
	 * @return int
	 */
	private function getCentralId( UserIdentity $user ): int {
		return $this->centralIdLookup->centralIdFromLocalUser(
			$user,
			CentralIdLookup::AUDIENCE_RAW
		);
	}

	/**
	 * @param User $user
	 * @return bool
	 */
	private function canUseCentralAuth( User $user ): bool {
		return $user->isSafeToLoad() &&
			$this->getCentralId( $user ) !== 0;
	}

	/**
	 * @param SourceUrl $sourceUrl
	 * @param User $user
	 * @return string
	 * @throws Exception
	 */
	private function getAuthorizedApiUrl(
		SourceUrl $sourceUrl,
		User $user
	): string {
		$url = $this->httpApiLookup->getApiUrl( $sourceUrl );
		return wfAppendQuery( $url, [
			'centralauthtoken' => $this->centralAuthTokenProvider->getToken( $user ),
		] );
	}

	/**
	 * @param SourceUrl $sourceUrl
	 * @param User $user
	 * @return string|null
	 */
	public function getCsrfToken( SourceUrl $sourceUrl, User $user ): ?string {
		try {
			$tokenRequestUrl = $this->getAuthorizedApiUrl( $sourceUrl, $user );
		} catch ( Exception $ex ) {
			$this->logger->error( 'Failed to get centralauthtoken: ' .
				$ex->getMessage() );
			return null;
		}
		$tokenRequest = $this->httpRequestExecutor->execute( $tokenRequestUrl, [
			'action' => 'query',
			'meta' => 'tokens',
			'type' => 'csrf',
			'format' => 'json',
			'formatversion' => 2,
		] );

		$tokenData = json_decode( $tokenRequest->getContent(), true );
		$token = $tokenData['query']['tokens']['csrftoken'] ?? null;

		if ( !$token ) {
			$this->logger->error( __METHOD__ . ' failed to get CSRF token.' );
		}

		return $token;
	}

	/**
	 * @param SourceUrl $sourceUrl
	 * @param User $user
	 * @param array $params API request params
	 * @param bool $usePost
	 * @return array|null Null in case of an error. Calling code can't understand why, but the error
	 *  is logged.
	 */
	private function doRequest(
		SourceUrl $sourceUrl,
		User $user,
		array $params,
		bool $usePost
	): ?array {
		/** @var array|null $result */
		$result = null;
		/** @var \MWHttpRequest|null $request */
		$request = null;

		try {
			$requestUrl = $this->getAuthorizedApiUrl( $sourceUrl, $user );
			$this->logger->debug( 'Got cross-wiki, authorized API URL: ' . $requestUrl );
			if ( $usePost ) {
				$request = $this->httpRequestExecutor->executePost( $requestUrl, $params );
			} else {
				$request = $this->httpRequestExecutor->execute( $requestUrl, $params );
			}
			$result = json_decode( $request->getContent(), true );
			if ( $result === null ) {
				$this->logger->error( __METHOD__ . ' failed to decode response from ' .
					$request->getFinalUrl() );
			}
		} catch ( Exception $ex ) {
			if ( !$request ) {
				$msg = __METHOD__ . ' failed to do remote request to ' . $sourceUrl->getHost() .
					' with params ' . json_encode( $params ) . ': ' . $ex->getMessage();
			} else {
				$msg = __METHOD__ . ' failed to do remote request to ' . $request->getFinalUrl() .
					' with params ' . json_encode( $params ) .
					' and response headers ' . json_encode( $request->getResponseHeaders() ) .
					': ' . $ex->getMessage();
			}
			$this->logger->error( $msg );
		}

		return $result;
	}

}
