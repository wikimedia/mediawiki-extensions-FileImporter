<?php

namespace FileImporter\Remote\MediaWiki;

use FileImporter\Data\FileRevision;
use FileImporter\Data\FileRevisions;
use FileImporter\Data\ImportDetails;
use FileImporter\Data\SourceUrl;
use FileImporter\Data\TextRevision;
use FileImporter\Data\TextRevisions;
use FileImporter\Exceptions\HttpRequestException;
use FileImporter\Exceptions\ImportException;
use FileImporter\Exceptions\LocalizedImportException;
use FileImporter\Interfaces\DetailRetriever;
use FileImporter\Services\Http\HttpRequestExecutor;
use Message;
use Psr\Log\LoggerInterface;
use Title;
use MediaWiki\MediaWikiServices;
use ConfigException;

/**
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class ApiDetailRetriever implements DetailRetriever {

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

	/**
	 * @var int
	 */
	private $maxBytes;

	/**
	 * @var int
	 */
	private $maxRevisions;

	/**
	* @var int
	*/
	private $maxAggregatedBytes;

	const MAXREVISIONS = 100;
	const MAXAGGREGATEDBYTES = 250000000;

	/**
	 * @param HttpApiLookup $httpApiLookup
	 * @param HttpRequestExecutor $httpRequestExecutor
	 * @param LoggerInterface $logger
	 * @param int $maxBytes
	 *
	 * @throws ConfigException
	 */
	public function __construct(
		HttpApiLookup $httpApiLookup,
		HttpRequestExecutor $httpRequestExecutor,
		LoggerInterface $logger,
		$maxBytes
	) {
		$this->httpApiLookup = $httpApiLookup;
		$this->httpRequestExecutor = $httpRequestExecutor;
		$this->logger = $logger;
		$this->maxBytes = $maxBytes;
		$services = MediaWikiServices::getInstance();
		$this->maxRevisions = (int)$services->getMainConfig()->get( 'FileImporterMaxRevisions' );
		$this->maxAggregatedBytes =
			(int)$services->getMainConfig()->get( 'FileImporterMaxAggregatedBytes' );
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
	 * @param string $apiUrl
	 * @param array $params
	 *
	 * @return string
	 */
	private function getRequestUrl( $apiUrl, array $params ) {
		return $apiUrl . '?' . http_build_query( $params );
	}

	/**
	 * @param string $requestUrl
	 *
	 * @return array
	 * @throws ImportException
	 */
	private function sendAPIRequest( $requestUrl ) {
		try {
			$imageInfoRequest = $this->httpRequestExecutor->execute( $requestUrl );
		} catch ( HttpRequestException $e ) {
			throw new LocalizedImportException(
				new Message( 'fileimporter-api-failedtogetinfo', [ $requestUrl ] )
			);
		}
		$requestData = json_decode( $imageInfoRequest->getContent(), true );
		return $requestData;
	}

	/**
	 * @param SourceUrl $sourceUrl
	 *
	 * @return ImportDetails
	 * @throws ImportException
	 */
	public function getImportDetails( SourceUrl $sourceUrl ) {
		$apiUrl = $this->httpApiLookup->getApiUrl( $sourceUrl );

		$params = $this->getBaseParams( $sourceUrl );
		$params = $this->addFileRevisionsToParams( $params );
		$params = $this->addTextRevisionsToParams( $params );

		$requestUrl = $this->getRequestUrl( $apiUrl, $params );
		$requestData = $this->sendAPIRequest( $requestUrl );

		if ( count( $requestData['query']['pages'] ) !== 1 ) {
			$this->logger->warning(
				'No pages returned by the API',
				[
					'sourceUrl' => $sourceUrl->getUrl(),
					'requestUrl' => $requestUrl,
				]
			);
			throw new LocalizedImportException( 'fileimporter-api-nopagesreturned' );
		}

		$pageInfoData = array_pop( $requestData['query']['pages'] );

		if ( array_key_exists( 'missing', $pageInfoData ) ) {
			if (
				array_key_exists( 'imagerepository', $pageInfoData ) &&
				$pageInfoData['imagerepository'] == 'shared'
			) {
				throw new LocalizedImportException( 'fileimporter-cantimportfromsharedrepo' );
			}
			throw new LocalizedImportException( 'fileimporter-cantimportmissingfile' );
		}

		$pageTitle = $pageInfoData['title'];

		$fileRevCount = count( $pageInfoData['imageinfo'] );
		$textRevCount = count( $pageInfoData['revisions'] );

		if ( !array_key_exists( 'imageinfo', $pageInfoData ) ||
			!array_key_exists( 'revisions', $pageInfoData ) ||
			$fileRevCount < 1 ||
			$textRevCount < 1
		) {
			$this->logger->warning(
				'Bad image or revision info returned by the API',
				[
					'sourceUrl' => $sourceUrl->getUrl(),
					'requestUrl' => $requestUrl,
				]
			);
			throw new LocalizedImportException( 'fileimporter-api-badinfo' );
		}

		$this->checkRevisionCount( $sourceUrl, $requestUrl, $pageInfoData );
		$this->checkMaxRevisionAggregatedBytes( $pageInfoData );

		while ( array_key_exists( 'continue', $requestData ) ) {
			$this->getMoreRevisions( $sourceUrl, $apiUrl, $requestData, $pageInfoData );
		}

		$imageInfoData = $pageInfoData['imageinfo'];
		$revisionsData = $pageInfoData['revisions'];
		$fileRevisions = $this->getFileRevisionsFromImageInfo( $imageInfoData, $pageTitle );
		$textRevisions = $this->getTextRevisionsFromRevisionsInfo( $revisionsData, $pageTitle );

		$splitTitle = explode( ':', $pageInfoData['title'] );
		$titleAfterColon = array_pop( $splitTitle );

		$importDetails = new ImportDetails(
			$sourceUrl,
			Title::newFromText( $titleAfterColon, NS_FILE ),
			$textRevisions,
			$fileRevisions
		);

		return $importDetails;
	}

	/**
	 * Fetches the next set of revisions unless the number of revisions
	 * exceeds the max revisions limit
	 *
	 * @param SourceUrl $sourceUrl
	 * @param string $apiUrl
	 * @param array &$requestData
	 * @param array &$pageInfoData
	 *
	 * @throws ImportException
	 */
	private function getMoreRevisions(
		SourceUrl $sourceUrl,
		$apiUrl,
		array &$requestData,
		array &$pageInfoData
	) {
		$rvContinue = array_key_exists( 'rvcontinue', $requestData['continue'] ) ?
			$requestData['continue']['rvcontinue'] : null;

		$iiStart = array_key_exists( 'iistart', $requestData['continue'] ) ?
			$requestData['continue']['iistart'] : null;

		$params = $this->getBaseParams( $sourceUrl );

		if ( $iiStart ) {
			$params = $this->addFileRevisionsToParams( $params, $iiStart );
		}

		if ( $rvContinue ) {
			$params = $this->addTextRevisionsToParams( $params, $rvContinue );
		}

		$requestUrl = $this->getRequestUrl( $apiUrl, $params );
		$requestData = $this->sendAPIRequest( $requestUrl );

		$newPageInfoData = array_pop( $requestData['query']['pages'] );

		if ( array_key_exists( 'revisions', $newPageInfoData ) ) {
			$pageInfoData['revisions'] =
				array_merge( $pageInfoData['revisions'], $newPageInfoData['revisions'] );
		}

		if ( array_key_exists( 'imageinfo', $newPageInfoData ) ) {
			$pageInfoData['imageinfo'] =
				array_merge( $pageInfoData['imageinfo'], $newPageInfoData['imageinfo'] );
		}

		$this->checkRevisionCount( $sourceUrl, $requestUrl, $pageInfoData );
		$this->checkMaxRevisionAggregatedBytes( $pageInfoData );
	}

	/**
	 * Throws an exception if the number of revisions to be imported exceeds
	 * the maximum revision limit
	 *
	 * @param SourceUrl $sourceUrl
	 * @param string $requestUrl
	 * @param array $pageInfoData
	 *
	 * @throws LocalizedImportException
	 */
	private function checkRevisionCount( SourceUrl $sourceUrl, $requestUrl, array $pageInfoData ) {
		if ( count( $pageInfoData['revisions'] ) > $this->maxRevisions ||
			count( $pageInfoData['imageinfo'] ) > $this->maxRevisions ||
			count( $pageInfoData['revisions'] ) > static::MAXREVISIONS ||
			count( $pageInfoData['imageinfo'] ) > static::MAXREVISIONS ) {
			$this->logger->warning(
				'Too many revisions were being fetched',
				[
					'sourceUrl' => $sourceUrl->getUrl(),
					'requestUrl' => $requestUrl,
				]
			);

			throw new LocalizedImportException( 'fileimporter-api-toomanyrevisions' );
		}
	}

	private function checkMaxRevisionAggregatedBytes( $pageInfoData ) {
		$aggregatedFileBytes = 0;
		foreach ( $pageInfoData['imageinfo'] as $fileVersion ) {
			$aggregatedFileBytes += $fileVersion['size'];
			if ( $aggregatedFileBytes > $this->maxAggregatedBytes ||
				$aggregatedFileBytes > static::MAXAGGREGATEDBYTES ) {
				$versions = count( $pageInfoData['imageinfo'] );
				throw new LocalizedImportException( [ 'fileimporter-filetoolarge', $versions ] );
			}
		}
	}

	/**
	 * @param array $imageInfo
	 * @param string $pageTitle
	 *
	 * @return FileRevisions
	 */
	private function getFileRevisionsFromImageInfo( array $imageInfo, $pageTitle ) {
		global $wgFileImporterAccountForSuppressedUsername;

		$revisions = [];
		foreach ( $imageInfo as $revisionInfo ) {
			if ( array_key_exists( 'filehidden', $revisionInfo ) ) {
				throw new LocalizedImportException( 'fileimporter-cantimportfilehidden' );
			}

			if ( array_key_exists( 'filemissing', $revisionInfo ) ) {
				throw new LocalizedImportException( 'fileimporter-filemissinginrevision' );
			}

			if ( array_key_exists( 'userhidden', $revisionInfo ) ) {
				$revisionInfo['user'] = $wgFileImporterAccountForSuppressedUsername;
			}

			if ( array_key_exists( 'sha1hidden', $revisionInfo ) ) {
				$revisionInfo['sha1'] = sha1( $revisionInfo['*'] );
			}

			if ( array_key_exists( 'size', $revisionInfo ) ) {
				if ( $revisionInfo['size'] > $this->maxBytes ) {
					$versions = count( $imageInfo );
					throw new LocalizedImportException( [ 'fileimporter-filetoolarge', $versions ] );
				}
			}

			/**
			 * Convert from API sha1 format to DB sha1 format.
			 * The conversion can be se inside ApiQueryImageInfo.
			 *  - API sha1 format is base 16 padded to 40 chars
			 *  - DB sha1 format is base 36 padded to 31 chars
			 */
			$revisionInfo['sha1'] = \Wikimedia\base_convert( $revisionInfo['sha1'], 16, 36, 31 );

			if ( array_key_exists( 'commenthidden', $revisionInfo ) ) {
				$revisionInfo['comment'] = (
					new Message( 'fileimporter-revision-removed-comment' )
				)->plain();
			}

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
		global $wgFileImporterAccountForSuppressedUsername;

		$revisions = [];
		foreach ( $revisionsInfo as $revisionInfo ) {
			if ( array_key_exists( 'userhidden', $revisionInfo ) ) {
				$revisionInfo['user'] = $wgFileImporterAccountForSuppressedUsername;
			}

			if ( array_key_exists( 'texthidden', $revisionInfo ) ) {
				$revisionInfo['*'] = (
					new Message( 'fileimporter-revision-removed-text' )
				)->plain();
			}

			if ( array_key_exists( 'sha1hidden', $revisionInfo ) ) {
				$revisionInfo['sha1'] = \Wikimedia\base_convert(
					sha1( $revisionInfo['*'] ), 16, 36, 31
				);
			}

			if ( array_key_exists( 'commenthidden', $revisionInfo ) ) {
				$revisionInfo['comment'] = (
					new Message( 'fileimporter-revision-removed-comment' )
				)->plain();
			}

			if ( !array_key_exists( 'contentmodel', $revisionInfo ) ) {
				$revisionInfo['contentmodel'] = CONTENT_MODEL_WIKITEXT;
			}

			if ( !array_key_exists( 'contentformat', $revisionInfo ) ) {
				$revisionInfo['contentformat'] = CONTENT_FORMAT_WIKITEXT;
			}

			$revisionInfo['minor'] = array_key_exists( 'minor', $revisionInfo );
			$revisionInfo['title'] = $pageTitle;
			$revisions[] = new TextRevision( $revisionInfo );
		}
		return new TextRevisions( $revisions );
	}

	/**
	 * Build params base
	 *
	 * @param SourceUrl $sourceUrl
	 * @return array
	 */
	private function getBaseParams( SourceUrl $sourceUrl ) {
		return [
			'action' => 'query',
			'format' => 'json',
			'titles' => $this->getTitleFromSourceUrl( $sourceUrl ),
			'prop' => ''
		];
	}

	/**
	 * Adds to params base the properties for getting Text Revisions
	 *
	 * @param array $params
	 * @param string|null $rvContinue
	 *
	 * @return array
	 */
	private function addTextRevisionsToParams( array $params, $rvContinue = null ) {
		$params['prop'] .= ( $params['prop'] ) ? "|revisions" : "revisions";

		if ( $rvContinue ) {
			$params['rvcontinue'] = $rvContinue;
		}

		return $params + [
			'rvlimit' => '500',
			'rvdir' => 'newer',
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
			)
		];
	}

	/**
	 * Adds to params base the properties for getting File Revisions
	 *
	 * @param array $params
	 * @param string|null $iiStart
	 *
	 * @return array
	 */
	private function addFileRevisionsToParams( array $params, $iiStart = null ) {
		$params['prop'] .= ( $params['prop'] ) ? "|imageinfo" : "imageinfo";

		if ( $iiStart ) {
			$params['iistart'] = $iiStart;
		}

		return $params + [
			'iilimit' => '500',
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
			)
		];
	}

}
