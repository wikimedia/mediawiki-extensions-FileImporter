<?php

namespace FileImporter\Remote\MediaWiki;

use FileImporter\Data\SourceUrl;
use FileImporter\Exceptions\HttpRequestException;
use FileImporter\Exceptions\ImportException;
use FileImporter\Interfaces\ImportTitleChecker;
use FileImporter\Remote\MediaWiki\HttpApiLookup;
use FileImporter\Services\HttpRequestExecutor;

class RemoteApiImportTitleChecker implements ImportTitleChecker {

	private $httpApiLookup;
	private $httpRequestExecutor;

	public function __construct(
		HttpApiLookup $httpApiLookup,
		HttpRequestExecutor $httpRequestExecutor
	) {
		$this->httpApiLookup = $httpApiLookup;
		$this->httpRequestExecutor = $httpRequestExecutor;
	}

	/**
	 * @param SourceUrl $sourceUrl
	 * @param string $intendedTitleString Foo.jpg or Berlin.png (NOT namespace prefixed)
	 *
	 * @return bool is the import allowed
	 */
	public function importAllowed( SourceUrl $sourceUrl, $intendedTitleString ) {
		$api = $this->httpApiLookup->getApiUrl( $sourceUrl );

		$requestUrl = $api . '?' . http_build_query( $this->getParams( $intendedTitleString ) );

		try {
			$imageInfoRequest = $this->httpRequestExecutor->execute( $requestUrl );
		} catch ( HttpRequestException $e ) {
			throw new ImportException( 'Failed to check title state from: ' . $requestUrl );
		}
		$requestData = json_decode( $imageInfoRequest->getContent(), true );

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
