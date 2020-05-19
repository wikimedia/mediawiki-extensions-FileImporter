<?php

namespace FileImporter\Remote\MediaWiki;

use FileImporter\Data\SourceUrl;
use FileImporter\Exceptions\HttpRequestException;
use FileImporter\Exceptions\ImportException;
use FileImporter\Interfaces\ImportTitleChecker;
use FileImporter\Services\Http\HttpRequestExecutor;
use Psr\Log\LoggerInterface;

/**
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class RemoteApiImportTitleChecker implements ImportTitleChecker {

	const ERROR_TITLE_STATE = 'noTitleStateFetched';

	private $httpApiLookup;
	private $httpRequestExecutor;
	private $logger;

	/**
	 * @param HttpApiLookup $httpApiLookup
	 * @param HttpRequestExecutor $httpRequestExecutor
	 * @param LoggerInterface $logger
	 */
	public function __construct(
		HttpApiLookup $httpApiLookup,
		HttpRequestExecutor $httpRequestExecutor,
		LoggerInterface $logger
	) {
		$this->httpApiLookup = $httpApiLookup;
		$this->httpRequestExecutor = $httpRequestExecutor;
		$this->logger = $logger;
	}

	/**
	 * @param SourceUrl $sourceUrl
	 * @param string $intendedTitleString Foo.jpg or Berlin.png (NOT namespace prefixed)
	 *
	 * @return bool is the import allowed
	 * @throws ImportException when the request failed
	 */
	public function importAllowed( SourceUrl $sourceUrl, $intendedTitleString ) {
		$api = $this->httpApiLookup->getApiUrl( $sourceUrl );
		$apiParameters = $this->getParams( $intendedTitleString );

		try {
			$imageInfoRequest = $this->httpRequestExecutor->execute( $api, $apiParameters );
		} catch ( HttpRequestException $e ) {
			$this->logger->error(
				__METHOD__ . ' failed to check title state from: ' . $api,
				[
					'url' => $e->getHttpRequest()->getFinalUrl(),
					'content' => $e->getHttpRequest()->getContent(),
					'errors' => $e->getStatusValue()->getErrors(),
					'apiUrl' => $api,
					'apiParameters' => $apiParameters,
				]
			);
			throw new ImportException(
				'Failed to check title state from: ' . $api, self::ERROR_TITLE_STATE );
		}

		$requestData = json_decode( $imageInfoRequest->getContent(), true );

		if ( !isset( $requestData['query']['pages'] ) ) {
			$this->logger->error( __METHOD__ . ' failed, could not find pages query key in result.' );
			return false;
		}

		// -1 appears as a key in all error situations, e.g. invalid title or page doesn't exist.
		return array_key_exists( '-1', $requestData['query']['pages'] );
	}

	private function getParams( $titleString ) {
		return [
			'format' => 'json',
			'action' => 'query',
			'prop' => 'revisions',
			'titles' => 'File:' . $titleString,
		];
	}

}
