<?php

namespace FileImporter\Remote\MediaWiki;

use FileImporter\Data\SourceUrl;
use FileImporter\Services\Http\HttpRequestExecutor;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use User;
use \Exception;
use \CentralIdLookup;

/**
 * Use CentralAuth to execute API calls on a sibling wiki.
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

	public function __construct(
		HttpApiLookup $httpApiLookup,
		HttpRequestExecutor $httpRequestExecutor,
		CentralAuthTokenProvider $centralAuthTokenProvider
	) {
		$this->httpApiLookup = $httpApiLookup;
		$this->httpRequestExecutor = $httpRequestExecutor;
		$this->centralAuthTokenProvider = $centralAuthTokenProvider;
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
	 * Execute a request
	 * @param SourceUrl $sourceUrl
	 * @param User $user
	 * @param array $params API request params
	 * @param bool $usePost
	 * @return array|null
	 */
	public function execute( SourceUrl $sourceUrl, User $user, $params, $usePost = false ) {
		// TODO handle error
		if ( !$this->canUseCentralAuth( $user ) ) {
			$this->logger->error( __METHOD__ . ' user can\'t use CentralAuth.' );
			return null;
		}

		return $this->doRequest( $sourceUrl, $user, $params, $usePost );
	}

	/**
	 * @param User $user
	 * @return int
	 */
	private function getCentralId( User $user ) {
		$lookup = CentralIdLookup::factory();
		$id = $lookup->centralIdFromLocalUser( $user, CentralIdLookup::AUDIENCE_RAW );
		return $id;
	}

	/**
	 * @param User $user
	 * @return bool
	 */
	private function canUseCentralAuth( User $user ) {
		return $user->isSafeToLoad() &&
			$this->getCentralId( $user ) !== 0;
	}

	/**
	 * @param SourceUrl $sourceUrl
	 * @param User $user
	 * @param array $additionalParams
	 * @return string
	 * @throws Exception
	 */
	private function getAuthorizedApiUrl(
		SourceUrl $sourceUrl,
		User $user,
		array $additionalParams = []
	) {
		$api = $this->httpApiLookup->getApiUrl( $sourceUrl );
		$additionalParams += [
			'centralauthtoken' => $this->centralAuthTokenProvider->getToken( $user ),
		];
		$requestUrl = $api . '?' . http_build_query( $additionalParams );
		return $requestUrl;
	}

	/**
	 * @param SourceUrl $sourceUrl
	 * @param User $user
	 * @return string
	 */
	public function getCsrfToken( SourceUrl $sourceUrl, User $user ) {
		try {
			$tokenRequestUrl = $this->getAuthorizedApiUrl( $sourceUrl, $user, [
				'action' => 'query',
				'meta' => 'tokens',
				'format' => 'json',
			] );
		} catch ( Exception $ex ) {
			$this->logger->error( 'Failed to get centralauthtoken: ' .
				$ex->getMessage() );
			return null;
		}
		$tokenRequest = $this->httpRequestExecutor->execute( $tokenRequestUrl );

		$tokenData = json_decode( $tokenRequest->getContent(), true );
		$token = $tokenData['query']['tokens']['csrftoken'] ?? null;

		if ( $token === null ) {
			$this->logger->error( __METHOD__ . ' failed to get CSRF token.' );
		}

		return $token;
	}

	/**
	 * @param SourceUrl $sourceUrl
	 * @param User $user
	 * @param array $params API request params
	 * @param bool $usePost
	 * @return array
	 */
	private function doRequest( SourceUrl $sourceUrl, User $user, array $params, $usePost ) {
		try {
			$requestUrl = $this->getAuthorizedApiUrl( $sourceUrl, $user );
			if ( $usePost ) {
				$request = $this->httpRequestExecutor->executePost( $requestUrl, $params );
			} else {
				$request = $this->httpRequestExecutor->execute( $requestUrl, $params );
			}
			return json_decode( $request->getContent(), true );
		} catch ( Exception $ex ) {
			$this->logger->error( __METHOD__ . 'failed to do remote request: ' .
				$ex->getMessage() );
			return null;
		}
	}

}