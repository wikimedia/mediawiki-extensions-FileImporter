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

	private const ERROR_TITLE_STATE = 'noTitleStateFetched';

	/** @var HttpApiLookup */
	private $httpApiLookup;
	/** @var HttpRequestExecutor */
	private $httpRequestExecutor;
	/** @var LoggerInterface */
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
	 * @param string $intendedFileName File name without the File: prefix
	 *
	 * @return bool false if a file with this name already exists
	 * @throws ImportException when the request failed
	 */
	public function importAllowed( SourceUrl $sourceUrl, string $intendedFileName ): bool {
		$api = $this->httpApiLookup->getApiUrl( $sourceUrl );
		$apiParameters = $this->getParams( $intendedFileName );

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
				'Failed to check title state from: ' . $api, self::ERROR_TITLE_STATE, $e );
		}

		$requestData = json_decode( $imageInfoRequest->getContent(), true );

		if ( !isset( $requestData['query']['pages'][0] ) ) {
			$this->logger->error( __METHOD__ . ' failed, could not find page in result.' );
			return false;
		}

		// Possible return values in output format version 2:
		// { "query": { "pages": [ { "pageid": 123, … when the title exists
		// { "query": { "pages": [ { "missing": true, … otherwise
		// Note the legacy format uses { "missing": "" }, and -1 etc. as keys for missing pages.
		return array_key_exists( 'missing', $requestData['query']['pages'][0] );
	}

	/**
	 * @param string $titleString
	 * @return array
	 */
	private function getParams( string $titleString ): array {
		return [
			'format' => 'json',
			'action' => 'query',
			'titles' => 'File:' . $titleString,
			'formatversion' => 2,
		];
	}

}
