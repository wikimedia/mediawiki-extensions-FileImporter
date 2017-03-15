<?php

namespace FileImporter\MediaWiki;

use FileImporter\Generic\HttpRequestExecutor;
use FileImporter\Generic\ImportAdjustments;
use FileImporter\Generic\ImportDetails;
use FileImporter\Generic\Importer;
use FileImporter\Generic\TargetUrl;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class ApiImporter implements Importer, LoggerAwareInterface {

	/**
	 * @var SiteTableSiteLookup
	 */
	private $siteTableSiteLookup;

	/**
	 * @var HttpApiLookup
	 */
	private $httpApiLookup;

	/**
	 * @var HttpRequestExecutor
	 */
	private $httpRequestExecutor;

	/**
	 * @var LoggerInterface
	 */
	private $logger;

	public function __construct(
		SiteTableSiteLookup $siteTableSiteLookup,
		HttpApiLookup $httpApiLookup,
		HttpRequestExecutor $httpRequestExecutor
	) {
		$this->siteTableSiteLookup = $siteTableSiteLookup;
		$this->httpApiLookup = $httpApiLookup;
		$this->httpRequestExecutor = $httpRequestExecutor;
		$this->logger = new NullLogger();
	}

	public function setLogger( LoggerInterface $logger ) {
		$this->logger = $logger;
	}

	/**
	 * @param TargetUrl $targetUrl
	 *
	 * @return bool
	 */
	public function canImport( TargetUrl $targetUrl ) {
		return $this->siteTableSiteLookup->getSite( $targetUrl->getParsedUrl()['host'] ) !== null;
	}

	/**
	 * @param TargetUrl $targetUrl
	 *
	 * @return ImportDetails
	 */
	public function getImportDetails( TargetUrl $targetUrl ) {
		$apiUrl = $this->httpApiLookup->getApiUrl( $targetUrl );

		// TODO catch and do something with exceptions?
		$imageInfoRequest = $this->httpRequestExecutor->execute(
			$apiUrl . '?' . http_build_query( $this->getImageInfoParams( $targetUrl ) )
		);
		$imageInfoData = json_decode( $imageInfoRequest->getContent(), true );

		if ( count( $imageInfoData['query']['pages'] ) !== 1 ) {
			// TODO log and exception
			die( 'unexpected number of pages returned?' );
		}

		$pageInfoData = array_pop( $imageInfoData['query']['pages'] );
		$normalizationData = array_pop( $imageInfoData['query']['normalized'] );
		$latestImageData = array_pop( $pageInfoData['imageinfo'] );

		$importDetails = new ImportDetails(
			$targetUrl,
			$normalizationData['to'],
			$latestImageData['thumburl']
		);

		return $importDetails;
	}

	private function getImageInfoParams( TargetUrl $targetUrl ) {
		$parsed = $targetUrl->getParsedUrl();
		// TODO what if the url is title=XXX?
		$bits = explode( '/', $parsed['path'] );
		$fullTitle = array_pop( $bits );

		return [
			'action' => 'query',
			'format' => 'json',
			'prop' => 'imageinfo',
			'titles' => $fullTitle,
			'iilimit' => '500',
			'iiurlwidth' => '500',
			'iiprop' => implode(
				'|',
				[
					// TODO are all of these actually needed?
					'timestamp',
					'user',
					'userid',
					'comment',
					'parsedcomment',
					'canonicaltitle',
					'url',
					'size',
					'dimensions',
					'sha1',
					'mime',
					'thumbmime',
					'mediatype',
					'metadata',
					'commonmetadata',
					'extmetadata',
					'archivename',
					'bitdepth',
					'uploadwarning',
					'badfile',
				]
			),
		];
	}

	/**
	 * @param TargetUrl $targetUrl
	 * @param ImportAdjustments $importAdjustments
	 *
	 * @return bool success
	 */
	public function import( TargetUrl $targetUrl, ImportAdjustments $importAdjustments ) {
		// TODO implement
		return false;
	}

}
