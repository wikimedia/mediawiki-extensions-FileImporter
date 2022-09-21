<?php

namespace FileImporter\Remote\MediaWiki;

use ConfigException;
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
use MediaWiki\MediaWikiServices;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use TitleValue;

/**
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class ApiDetailRetriever implements DetailRetriever {
	use MediaWikiSourceUrlParser;

	/**
	 * @var HttpApiLookup
	 */
	private $httpApiLookup;

	/**
	 * @var HttpRequestExecutor
	 */
	private $httpRequestExecutor;

	/**
	 * @var int
	 */
	private $maxBytes;

	/**
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * @var string Placeholder name replacing usernames that have been suppressed as part of
	 * a steward action on the source site.
	 */
	private $suppressedUsername;

	/**
	 * @var int
	 */
	private $maxRevisions;

	/**
	 * @var int
	 */
	private $maxAggregatedBytes;

	private const API_RESULT_LIMIT = 500;
	private const MAX_REVISIONS = 100;
	private const MAX_AGGREGATED_BYTES = 250000000;

	/**
	 * @param HttpApiLookup $httpApiLookup
	 * @param HttpRequestExecutor $httpRequestExecutor
	 * @param int $maxBytes
	 * @param LoggerInterface|null $logger
	 *
	 * @throws ConfigException when $wgFileImporterAccountForSuppressedUsername is invalid
	 */
	public function __construct(
		HttpApiLookup $httpApiLookup,
		HttpRequestExecutor $httpRequestExecutor,
		$maxBytes,
		LoggerInterface $logger = null
	) {
		$this->httpApiLookup = $httpApiLookup;
		$this->httpRequestExecutor = $httpRequestExecutor;
		$this->maxBytes = $maxBytes;
		$this->logger = $logger ?: new NullLogger();

		$config = MediaWikiServices::getInstance()->getMainConfig();

		$this->maxRevisions = (int)$config->get( 'FileImporterMaxRevisions' );
		$this->maxAggregatedBytes = (int)$config->get( 'FileImporterMaxAggregatedBytes' );
		$this->suppressedUsername = $config->get( 'FileImporterAccountForSuppressedUsername' );
		if ( !MediaWikiServices::getInstance()->getUserNameUtils()->isValid( $this->suppressedUsername ) ) {
			throw new ConfigException(
				'Invalid username configured in wgFileImporterAccountForSuppressedUsername: "' .
				$this->suppressedUsername . '"'
			);
		}
	}

	/**
	 * @param SourceUrl $sourceUrl
	 * @param array $apiParameters
	 *
	 * @return array[]
	 * @throws ImportException when the request failed
	 */
	private function sendApiRequest( SourceUrl $sourceUrl, array $apiParameters ) {
		$apiUrl = $this->httpApiLookup->getApiUrl( $sourceUrl );

		try {
			$imageInfoRequest = $this->httpRequestExecutor->execute( $apiUrl, $apiParameters );
		} catch ( HttpRequestException $e ) {
			throw new LocalizedImportException( [ 'fileimporter-api-failedtogetinfo',
				$apiUrl ], $e );
		}
		$requestData = json_decode( $imageInfoRequest->getContent(), true );
		return $requestData;
	}

	/**
	 * @param SourceUrl $sourceUrl
	 *
	 * @return ImportDetails
	 * @throws ImportException e.g. when the file couldn't be found
	 */
	public function getImportDetails( SourceUrl $sourceUrl ): ImportDetails {
		$params = $this->getBaseParams( $sourceUrl );
		$params = $this->addFileRevisionsToParams( $params );
		$params = $this->addTextRevisionsToParams( $params );
		$params = $this->addTemplatesToParams( $params );
		$params = $this->addCategoriesToParams( $params );

		$requestData = $this->sendApiRequest( $sourceUrl, $params );

		if ( !isset( $requestData['query'] ) || count( $requestData['query']['pages'] ) !== 1 ) {
			$this->logger->warning(
				'No pages returned by the API',
				[
					'sourceUrl' => $sourceUrl->getUrl(),
					'apiParameters' => $params,
				]
			);
			throw new LocalizedImportException( 'fileimporter-api-nopagesreturned' );
		}

		/** @var array $pageInfoData */
		$pageInfoData = end( $requestData['query']['pages'] );
		'@phan-var array $pageInfoData';

		if ( array_key_exists( 'missing', $pageInfoData ) ) {
			if (
				array_key_exists( 'imagerepository', $pageInfoData ) &&
				$pageInfoData['imagerepository'] == 'shared'
			) {
				throw new LocalizedImportException(
					[ 'fileimporter-cantimportfromsharedrepo', $sourceUrl->getHost() ]
				);
			}
			throw new LocalizedImportException( 'fileimporter-cantimportmissingfile' );
		}

		if ( empty( $pageInfoData['imageinfo'] ) || empty( $pageInfoData['revisions'] ) ) {
			$this->logger->warning(
				'Bad image or revision info returned by the API',
				[
					'sourceUrl' => $sourceUrl->getUrl(),
					'apiParameters' => $params,
				]
			);
			throw new LocalizedImportException( 'fileimporter-api-badinfo' );
		}

		// FIXME: Isn't this misplaced here, *before* more revisions are fetched?
		$this->checkRevisionCount( $sourceUrl, $pageInfoData );
		$this->checkMaxRevisionAggregatedBytes( $pageInfoData );

		while ( array_key_exists( 'continue', $requestData ) ) {
			$this->getMoreRevisions( $sourceUrl, $requestData, $pageInfoData );
		}

		$pageTitle = $pageInfoData['title'];
		$pageLanguage = $pageInfoData['pagelanguagehtmlcode'] ?? null;

		$imageInfoData = $pageInfoData['imageinfo'];
		$revisionsData = $pageInfoData['revisions'];
		$fileRevisions = $this->getFileRevisionsFromImageInfo( $imageInfoData, $pageTitle );
		$textRevisions = $this->getTextRevisionsFromRevisionsInfo( $revisionsData, $pageTitle );
		$templates = $this->reduceTitleList( $pageInfoData['templates'] ?? [], NS_TEMPLATE );
		$categories = $this->reduceTitleList( $pageInfoData['categories'] ?? [], NS_CATEGORY );

		$splitTitle = explode( ':', $pageInfoData['title'] );
		$titleAfterColon = end( $splitTitle );

		$importDetails = new ImportDetails(
			$sourceUrl,
			new TitleValue( NS_FILE, $titleAfterColon ),
			$textRevisions,
			$fileRevisions
		);
		// FIXME: Better use constructor parameters instead of setters?
		$importDetails->setPageLanguage( $pageLanguage );
		$importDetails->setTemplates( $templates );
		$importDetails->setCategories( $categories );

		return $importDetails;
	}

	/**
	 * @param array[] $titles
	 * @param int $namespace
	 *
	 * @return string[]
	 */
	private function reduceTitleList( array $titles, $namespace ) {
		return array_map(
			static function ( array $title ) {
				return $title['title'];
			},
			array_filter(
				$titles,
				static function ( array $title ) use ( $namespace ) {
					return $title['ns'] === $namespace;
				}
			)
		);
	}

	/**
	 * Fetches the next set of revisions unless the number of revisions
	 * exceeds the max revisions limit
	 *
	 * @param SourceUrl $sourceUrl
	 * @param array[] &$requestData
	 * @param array[] &$pageInfoData
	 *
	 * @throws ImportException
	 */
	private function getMoreRevisions(
		SourceUrl $sourceUrl,
		array &$requestData,
		array &$pageInfoData
	) {
		$rvContinue = $requestData['continue']['rvcontinue'] ?? null;
		$iiStart = $requestData['continue']['iistart'] ?? null;
		$tlContinue = $requestData['continue']['tlcontinue'] ?? null;
		$clContinue = $requestData['continue']['clcontinue'] ?? null;

		$params = $this->getBaseParams( $sourceUrl );

		if ( $iiStart ) {
			$params = $this->addFileRevisionsToParams( $params, $iiStart );
		}

		if ( $rvContinue ) {
			$params = $this->addTextRevisionsToParams( $params, $rvContinue );
		}

		if ( $tlContinue ) {
			$params = $this->addTemplatesToParams( $params, $tlContinue );
		}

		if ( $clContinue ) {
			$params = $this->addCategoriesToParams( $params, $clContinue );
		}

		$requestData = $this->sendApiRequest( $sourceUrl, $params );

		$newPageInfoData = end( $requestData['query']['pages'] );

		if ( array_key_exists( 'revisions', $newPageInfoData ) ) {
			$pageInfoData['revisions'] =
				array_merge( $pageInfoData['revisions'], $newPageInfoData['revisions'] );
		}

		if ( array_key_exists( 'imageinfo', $newPageInfoData ) ) {
			$pageInfoData['imageinfo'] =
				array_merge( $pageInfoData['imageinfo'], $newPageInfoData['imageinfo'] );
		}

		if ( array_key_exists( 'templates', $newPageInfoData ) ) {
			$pageInfoData['templates'] =
				array_merge( $pageInfoData['templates'], $newPageInfoData['templates'] );
		}

		if ( array_key_exists( 'categories', $newPageInfoData ) ) {
			$pageInfoData['categories'] =
				array_merge( $pageInfoData['categories'], $newPageInfoData['categories'] );
		}

		$this->checkRevisionCount( $sourceUrl, $pageInfoData );
		$this->checkMaxRevisionAggregatedBytes( $pageInfoData );
	}

	/**
	 * Throws an exception if the number of revisions to be imported exceeds
	 * the maximum revision limit
	 *
	 * @param SourceUrl $sourceUrl
	 * @param array[] $pageInfoData
	 *
	 * @throws ImportException when exceeding the acceptable maximum
	 */
	private function checkRevisionCount( SourceUrl $sourceUrl, array $pageInfoData ) {
		if ( count( $pageInfoData['revisions'] ) > $this->maxRevisions ||
			count( $pageInfoData['imageinfo'] ) > $this->maxRevisions ||
			count( $pageInfoData['revisions'] ) > static::MAX_REVISIONS ||
			count( $pageInfoData['imageinfo'] ) > static::MAX_REVISIONS ) {
			$this->logger->warning(
				'Too many revisions were being fetched',
				[
					'sourceUrl' => $sourceUrl->getUrl(),
				]
			);

			throw new LocalizedImportException( 'fileimporter-api-toomanyrevisions' );
		}
	}

	/**
	 * @param array[] $pageInfoData
	 * @phan-param array{imageinfo:array{size:int}[]} $pageInfoData
	 *
	 * @throws ImportException when exceeding the maximum file size
	 */
	private function checkMaxRevisionAggregatedBytes( array $pageInfoData ) {
		$aggregatedFileBytes = 0;
		foreach ( $pageInfoData['imageinfo'] as $fileVersion ) {
			$aggregatedFileBytes += $fileVersion['size'] ?? 0;
			if ( $aggregatedFileBytes > $this->maxAggregatedBytes ||
				$aggregatedFileBytes > static::MAX_AGGREGATED_BYTES ) {
				$versions = count( $pageInfoData['imageinfo'] );
				throw new LocalizedImportException( [ 'fileimporter-filetoolarge', $versions ] );
			}
		}
	}

	/**
	 * @param array[] $imageInfo
	 * @param string $pageTitle
	 *
	 * @return FileRevisions
	 * @throws ImportException when the file is not acceptable, e.g. hidden or to big
	 */
	private function getFileRevisionsFromImageInfo( array $imageInfo, $pageTitle ) {
		$revisions = [];
		foreach ( $imageInfo as $revisionInfo ) {
			if ( array_key_exists( 'filehidden', $revisionInfo ) ) {
				throw new LocalizedImportException( 'fileimporter-cantimportfilehidden' );
			}

			if ( array_key_exists( 'filemissing', $revisionInfo ) ) {
				throw new LocalizedImportException( 'fileimporter-filemissinginrevision' );
			}

			if ( array_key_exists( 'userhidden', $revisionInfo ) ) {
				$revisionInfo['user'] = $this->suppressedUsername;
			}

			if ( isset( $revisionInfo['size'] ) && $revisionInfo['size'] > $this->maxBytes ) {
				$versions = count( $imageInfo );
				throw new LocalizedImportException( [ 'fileimporter-filetoolarge', $versions ] );
			}

			if ( isset( $revisionInfo['sha1'] ) ) {
				// Convert from API sha1 format to DB sha1 format. The conversion can be se inside
				// ApiQueryImageInfo.
				// * API sha1 format is base 16 padded to 40 chars
				// * DB sha1 format is base 36 padded to 31 chars
				$revisionInfo['sha1'] = \Wikimedia\base_convert( $revisionInfo['sha1'], 16, 36, 31 );
			}

			if ( array_key_exists( 'commenthidden', $revisionInfo ) ) {
				$revisionInfo['comment'] = wfMessage( 'fileimporter-revision-removed-comment' )
					->plain();
			}

			$revisionInfo['name'] = $pageTitle;
			$revisionInfo['description'] = $revisionInfo['comment'] ?? null;

			$revisions[] = new FileRevision( $revisionInfo );
		}
		return new FileRevisions( $revisions );
	}

	/**
	 * @param array[] $revisionsInfo
	 * @param string $pageTitle
	 *
	 * @return TextRevisions
	 */
	private function getTextRevisionsFromRevisionsInfo( array $revisionsInfo, $pageTitle ) {
		$revisions = [];
		foreach ( $revisionsInfo as $revisionInfo ) {
			if ( array_key_exists( 'userhidden', $revisionInfo ) ) {
				$revisionInfo['user'] = $this->suppressedUsername;
			}

			if ( array_key_exists( 'texthidden', $revisionInfo ) ) {
				$revisionInfo['*'] = wfMessage( 'fileimporter-revision-removed-text' )
					->plain();
			}

			if ( array_key_exists( 'commenthidden', $revisionInfo ) ) {
				$revisionInfo['comment'] = wfMessage( 'fileimporter-revision-removed-comment' )
					->plain();
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
	 * @param SourceUrl $sourceUrl
	 * @return string[]
	 */
	private function getBaseParams( SourceUrl $sourceUrl ) {
		return [
			'action' => 'query',
			'format' => 'json',
			'titles' => $this->parseTitleFromSourceUrl( $sourceUrl ),
			'prop' => 'info'
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
		$params['prop'] .= ( $params['prop'] ) ? '|revisions' : 'revisions';

		if ( $rvContinue ) {
			$params['rvcontinue'] = $rvContinue;
		}

		return $params + [
			'rvlimit' => static::API_RESULT_LIMIT,
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
					'tags',
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
		$params['prop'] .= ( $params['prop'] ) ? '|imageinfo' : 'imageinfo';

		if ( $iiStart ) {
			$params['iistart'] = $iiStart;
		}

		return $params + [
			'iilimit' => static::API_RESULT_LIMIT,
			'iiurlwidth' => 800,
			'iiurlheight' => 400,
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
					'archivename',
				]
			)
		];
	}

	/**
	 * Adds to params base the properties for getting Templates
	 *
	 * @param array $params
	 * @param string|null $tlContinue
	 *
	 * @return array
	 */
	private function addTemplatesToParams( array $params, $tlContinue = null ) {
		$params['prop'] .= ( $params['prop'] ) ? '|templates' : 'templates';

		if ( $tlContinue ) {
			$params['tlcontinue'] = $tlContinue;
		}

		return $params + [ 'tlnamespace' => NS_TEMPLATE, 'tllimit' => static::API_RESULT_LIMIT ];
	}

	/**
	 * Adds to params base the properties for getting Categories
	 *
	 * @param array $params
	 * @param string|null $clContinue
	 *
	 * @return array
	 */
	private function addCategoriesToParams( array $params, $clContinue = null ) {
		$params['prop'] .= ( $params['prop'] ) ? '|categories' : 'categories';

		if ( $clContinue ) {
			$params['clcontinue'] = $clContinue;
		}

		return $params + [ 'cllimit' => static::API_RESULT_LIMIT ];
	}

}
