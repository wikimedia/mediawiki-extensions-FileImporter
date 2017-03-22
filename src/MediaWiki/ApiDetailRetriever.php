<?php

namespace FileImporter\MediaWiki;

use FileImporter\Generic\Data\FileRevisions;
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
		return $this->siteTableSiteLookup->getSite( $targetUrl->getParsedUrl()['host'] ) !== null &&
			$this->getTitleFromTargetUrl( $targetUrl ) !== null;
	}

	/**
	 * @param TargetUrl $targetUrl
	 * @return string|null the string title extracted or null on failure
	 */
	private function getTitleFromTargetUrl( TargetUrl $targetUrl ) {
		$parsed = $targetUrl->getParsedUrl();
		$title = null;
		$hasQueryAndTitle = null;

		if ( array_key_exists( 'query', $parsed ) ) {
			parse_str( $parsed['query'], $bits );
			$hasQueryAndTitle = array_key_exists( 'title', $bits );
			if ( $hasQueryAndTitle && strlen( $bits['title'] ) > 0 ) {
				$title = $bits['title'];
			}
		}

		if ( !$hasQueryAndTitle && array_key_exists( 'path', $parsed ) ) {
			$bits = explode( '/', $parsed['path'] );
			if ( count( $bits ) >= 2 && !empty( $bits[count( $bits ) - 1] ) ) {
				$title = array_pop( $bits );
			}
		}

		return $title;
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
		$pageTitle = $pageInfoData['title'];

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
		$fileRevisions = $this->getFileRevisionsFromImageInfo( $imageInfoData, $pageTitle );
		$textRevisions = $this->getTextRevisionsFromRevisionsInfo( $revisionsData, $pageTitle );

		$importDetails = new ImportDetails(
			$targetUrl,
			$normalizationData['to'],
			$fileRevisions->getLatest()->getField( 'thumburl' ),
			$textRevisions,
			$fileRevisions
		);

		return $importDetails;
	}

	/**
	 * @param array $imageInfo
	 * @param string $pageTitle
	 *
	 * @return FileRevisions
	 */
	private function getFileRevisionsFromImageInfo( array $imageInfo, $pageTitle ) {
		$revisions = [];
		foreach ( $imageInfo as $revisionInfo ) {
			/**
			 * Convert from API sha1 format to DB sha1 format.
			 * The conversion can be se inside ApiQueryImageInfo.
			 *  - API sha1 format is base 16 padded to 40 chars
			 *  - DB sha1 format is base 36 padded to 31 chars
			 */
			$revisionInfo['sha1'] = \Wikimedia\base_convert( $revisionInfo['sha1'], 16, 36, 31 );
			$revisionInfo['bits'] = $revisionInfo['size'];
			$revisionInfo['user_text'] = $revisionInfo['user'];
			$revisionInfo['user'] = $revisionInfo['userid'];
			$revisionInfo['name'] = $pageTitle;
			$revisionInfo['description'] = $revisionInfo['comment'];

			$revisions[] = new FileRevision( $revisionInfo );
		}
		return new FileRevisions( $revisions );
	}

	/**
	 * @param array $revisionsInfo
	 * @param string $pageTitle
	 *
	 * @return TextRevision[]
	 */
	private function getTextRevisionsFromRevisionsInfo( array $revisionsInfo, $pageTitle ) {
		$revisions = [];
		foreach ( $revisionsInfo as $revisionInfo ) {
			$revisionInfo['minor'] = array_key_exists( 'minor', $revisionInfo );
			$revisionInfo['title'] = $pageTitle;
			$revisions[] = new TextRevision( $revisionInfo );
		}
		return $revisions;
	}

	private function getParams( TargetUrl $targetUrl ) {
		return [
			'action' => 'query',
			'format' => 'json',
			'prop' => 'imageinfo|revisions',
			'titles' => $this->getTitleFromTargetUrl( $targetUrl ),
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
