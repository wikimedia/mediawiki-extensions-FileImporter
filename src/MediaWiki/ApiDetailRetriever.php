<?php

namespace FileImporter\MediaWiki;

use FileImporter\Generic\Exceptions\HttpRequestException;
use FileImporter\Generic\Exceptions\ImportException;
use FileImporter\Generic\Data\FileRevision;
use FileImporter\Generic\Services\HttpRequestExecutor;
use FileImporter\Generic\Data\ImportDetails;
use FileImporter\Generic\Services\DetailRetriever;
use FileImporter\Generic\Data\TargetUrl;
use FileImporter\Generic\Data\TextRevision;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class ApiDetailRetriever implements DetailRetriever, LoggerAwareInterface {

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
	public function canGetImportDetails( TargetUrl $targetUrl ) {
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

		if ( array_key_exists( 'continue', $requestData ) ) {
			$this->logger->warning(
				'API returned continue data',
				[
					'targetUrl' => $targetUrl->getUrl(),
					'requestUrl' => $requestUrl,
				]
			);
			// TODO support continuation
			throw new ImportException( 'Too many revisions, can not import.' );
		}

		if ( count( $requestData['query']['pages'] ) !== 1 ) {
			$this->logger->warning(
				'No pages returned by the API',
				[
					'targetUrl' => $targetUrl->getUrl(),
					'requestUrl' => $requestUrl,
				]
			);
			throw new ImportException( 'No pages returned by the API' );
		}

		$pageInfoData = array_pop( $requestData['query']['pages'] );

		if ( !array_key_exists( 'imageinfo', $pageInfoData ) ||
			!array_key_exists( 'revisions', $pageInfoData ) ||
			count( $pageInfoData['imageinfo'] ) < 1 ||
			count( $pageInfoData['revisions'] ) < 1
		) {
			$this->logger->warning(
				'Bad image or revision info returned by the API',
				[
					'targetUrl' => $targetUrl->getUrl(),
					'requestUrl' => $requestUrl,
				]
			);
			throw new ImportException( 'Bad image or revision info returned by the API' );
		}

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

		if ( array_key_exists( 'query', $parsed ) && strstr( $parsed['query'], 'title' ) ) {
			parse_str( $parsed['query'], $bits );
			$fullTitle = $bits['title'];
		} else {
			$bits = explode( '/', $parsed['path'] );
			$fullTitle = array_pop( $bits );
		}

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

}
