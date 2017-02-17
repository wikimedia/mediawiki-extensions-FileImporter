<?php

namespace FileImporter\MediaWiki;

use FileImporter\Generic\Exceptions\HttpRequestException;
use FileImporter\Generic\Exceptions\ImportException;
use FileImporter\Generic\FileRevision;
use FileImporter\Generic\HttpRequestExecutor;
use FileImporter\Generic\ImportAdjustments;
use FileImporter\Generic\ImportDetails;
use FileImporter\Generic\Importer;
use FileImporter\Generic\TargetUrl;
use FileImporter\Generic\TextRevision;
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
	 * @throws ImportException
	 */
	public function getImportDetails( TargetUrl $targetUrl ) {
		$apiUrl = $this->httpApiLookup->getApiUrl( $targetUrl );

		$requestUrl = $apiUrl . '?' . http_build_query( $this->getParams( $targetUrl ) );
		try {
			$imageInfoRequest = $this->httpRequestExecutor->execute( $requestUrl );
		} catch ( HttpRequestException $e ) {
			throw new ImportException( 'Failed to retrieve file information from: ' . $requestUrl );
		}
		$requestData = json_decode( $imageInfoRequest->getContent(), true );
		// TODO check if the response has any continuation data. Either continue or die here...

		if ( count( $requestData['query']['pages'] ) !== 1 ) {
			// TODO log?
			throw new ImportException( 'Unexpected number of pages received from the API.' );
		}

		$pageInfoData = array_pop( $requestData['query']['pages'] );
		$normalizationData = array_pop( $requestData['query']['normalized'] );
		$imageInfoData = $pageInfoData['imageinfo'];
		$revisionsData = $pageInfoData['revisions'];
		$fileRevisions = $this->getFileRevisionsFromImageInfo( $imageInfoData );
		$textRevisions = $this->getTextRevisionsFromRevisionsInfo( $revisionsData );

		$importDetails = new ImportDetails(
			$targetUrl,
			$normalizationData['to'],
			$fileRevisions[0]->getField( 'thumburl' ),
			$textRevisions,
			$fileRevisions
		);

		return $importDetails;
	}

	/**
	 * @param array $imageInfo
	 *
	 * @return FileRevision[]
	 */
	private function getFileRevisionsFromImageInfo( array $imageInfo ) {
		$revisions = [];
		foreach ( $imageInfo as $revisionInfo ) {
			$revisions[] = new FileRevision( $revisionInfo );
		}
		return $revisions;
	}

	/**
	 * @param array $revisionsInfo
	 *
	 * @return TextRevision[]
	 */
	private function getTextRevisionsFromRevisionsInfo( array $revisionsInfo ) {
		$revisions = [];
		foreach ( $revisionsInfo as $revisionInfo ) {
			$revisions[] = new TextRevision( $revisionInfo );
		}
		return $revisions;
	}

	private function getParams( TargetUrl $targetUrl ) {
		$parsed = $targetUrl->getParsedUrl();
		// TODO what if the url is title=XXX?
		$bits = explode( '/', $parsed['path'] );
		$fullTitle = array_pop( $bits );

		return [
			'action' => 'query',
			'format' => 'json',
			'prop' => 'imageinfo|revisions',
			'titles' => $fullTitle,
			'iilimit' => '500',
			'rvlimit' => '500',
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
			'rvprop' => implode(
				'|',
				[
					// TODO are all of these actually needed?
					'ids',
					'flags',
					'timestamp',
					'user',
					'userid',
					'size',
					'sha1',
					'contentmodel',
					'comment',
					'parsedcomment',
					'content',
					'tags',
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
