<?php

namespace FileImporter\MediaWiki;

use FileImporter\Data\FileRevision;
use FileImporter\Data\FileRevisions;
use FileImporter\Data\ImportDetails;
use FileImporter\Data\SourceUrl;
use FileImporter\Data\TextRevision;
use FileImporter\Data\TextRevisions;
use FileImporter\Exceptions\HttpRequestException;
use FileImporter\Exceptions\ImportException;
use FileImporter\Interfaces\DetailRetriever;
use FileImporter\Services\HttpRequestExecutor;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Title;

class ApiDetailRetriever implements DetailRetriever, LoggerAwareInterface {

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
		HttpApiLookup $httpApiLookup,
		HttpRequestExecutor $httpRequestExecutor
	) {
		$this->httpApiLookup = $httpApiLookup;
		$this->httpRequestExecutor = $httpRequestExecutor;
		$this->logger = new NullLogger();
	}

	public function setLogger( LoggerInterface $logger ) {
		$this->logger = $logger;
	}

	/**
	 * @param SourceUrl $sourceUrl
	 * @return string|null the string title extracted or null on failure
	 */
	private function getTitleFromSourceUrl( SourceUrl $sourceUrl ) {
		$parsed = $sourceUrl->getParsedUrl();
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
	 * @param SourceUrl $sourceUrl
	 *
	 * @return ImportDetails
	 * @throws ImportException
	 */
	public function getImportDetails( SourceUrl $sourceUrl ) {
		$apiUrl = $this->httpApiLookup->getApiUrl( $sourceUrl );

		$requestUrl = $apiUrl . '?' . http_build_query( $this->getParams( $sourceUrl ) );
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
					'sourceUrl' => $sourceUrl->getUrl(),
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
					'sourceUrl' => $sourceUrl->getUrl(),
					'requestUrl' => $requestUrl,
				]
			);
			throw new ImportException( 'No pages returned by the API' );
		}

		$pageInfoData = array_pop( $requestData['query']['pages'] );

		if ( array_key_exists( 'missing', $pageInfoData ) ) {
			if (
				array_key_exists( 'imagerepository', $pageInfoData ) &&
				$pageInfoData['imagerepository'] == 'shared'
			) {
				throw new ImportException( 'Can not import a file from a share repository.' );
			}
			throw new ImportException( 'Can not import a missing file.' );
		}

		$pageTitle = $pageInfoData['title'];

		if ( !array_key_exists( 'imageinfo', $pageInfoData ) ||
			!array_key_exists( 'revisions', $pageInfoData ) ||
			count( $pageInfoData['imageinfo'] ) < 1 ||
			count( $pageInfoData['revisions'] ) < 1
		) {
			$this->logger->warning(
				'Bad image or revision info returned by the API',
				[
					'sourceUrl' => $sourceUrl->getUrl(),
					'requestUrl' => $requestUrl,
				]
			);
			throw new ImportException( 'Bad image or revision info returned by the API' );
		}

		$imageInfoData = $pageInfoData['imageinfo'];
		$revisionsData = $pageInfoData['revisions'];
		$fileRevisions = $this->getFileRevisionsFromImageInfo( $imageInfoData, $pageTitle );
		$textRevisions = $this->getTextRevisionsFromRevisionsInfo( $revisionsData, $pageTitle );

		$importDetails = new ImportDetails(
			$sourceUrl,
			Title::newFromText( $pageInfoData['title'], NS_FILE ),
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
	 * @return TextRevisions
	 */
	private function getTextRevisionsFromRevisionsInfo( array $revisionsInfo, $pageTitle ) {
		$revisions = [];
		foreach ( $revisionsInfo as $revisionInfo ) {
			if ( array_key_exists( 'suppressed', $revisionInfo ) ) {
				// TODO allow importing revisions with suppressed content T162255
				throw new ImportException( 'Can not import revisions with suppressed content.' );
			}

			$revisionInfo['minor'] = array_key_exists( 'minor', $revisionInfo );
			$revisionInfo['title'] = $pageTitle;
			$revisions[] = new TextRevision( $revisionInfo );
		}
		return new TextRevisions( $revisions );
	}

	private function getParams( SourceUrl $sourceUrl ) {
		return [
			'action' => 'query',
			'format' => 'json',
			'prop' => 'imageinfo|revisions',
			'titles' => $this->getTitleFromSourceUrl( $sourceUrl ),
			'iilimit' => '500',
			'rvlimit' => '500',
			'iiurlwidth' => '800',
			'iiurlheight' => '400',
			'iiprop' => implode(
				'|',
				[
					'timestamp',
					'user',
					'userid',
					'comment',
					'canonicaltitle',
					'url',
					'size',
					'sha1',
				]
			),
			'rvprop' => implode(
				'|',
				[
					'flags',
					'timestamp',
					'user',
					'sha1',
					'contentmodel',
					'comment',
					'content',
				]
			),
		];
	}

}
