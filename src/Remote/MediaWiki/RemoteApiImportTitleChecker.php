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

	private $httpApiLookup;
	private $httpRequestExecutor;
	private $logger;

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
	 */
	public function importAllowed( SourceUrl $sourceUrl, $intendedTitleString ) {
		$api = $this->httpApiLookup->getApiUrl( $sourceUrl );

		$requestUrl = $api . '?' . http_build_query( $this->getParams( $intendedTitleString ) );

		try {
			$imageInfoRequest = $this->httpRequestExecutor->execute( $requestUrl );
		} catch ( HttpRequestException $e ) {
			$this->logger->error(
				__METHOD__ . ' failed to check title state from: ' . $requestUrl,
				[
					'url' => $e->getHttpRequest()->getFinalUrl(),
					'content' => $e->getHttpRequest()->getContent(),
					'errors' => $e->getStatusValue()->getErrors(),
				]
			);
			throw new ImportException( 'Failed to check title state from: ' . $requestUrl );
		}
		$requestData = json_decode( $imageInfoRequest->getContent(), true );

		$result = array_key_exists( '-1', $requestData['query']['pages'] );
		if ( !$result ) {
			$this->logger->error( __METHOD__ . ' failed, could not find pages query key in result.' );
		}

		return $result;
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
