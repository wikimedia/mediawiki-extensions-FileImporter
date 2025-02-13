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
use MediaWiki\Config\ConfigException;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Title\TitleValue;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class ApiDetailRetriever implements DetailRetriever {
	use MediaWikiSourceUrlParser;

	private HttpApiLookup $httpApiLookup;
	private HttpRequestExecutor $httpRequestExecutor;
	private int $maxBytes;
	private LoggerInterface $logger;
	/**
	 * @var string Placeholder name replacing usernames that have been suppressed as part of
	 * a steward action on the source site.
	 */
	private $suppressedUsername;
	private int $maxRevisions;
	private int $maxAggregatedBytes;

	private const API_RESULT_LIMIT = 500;
	private const MAX_REVISIONS = 100;
	private const MAX_AGGREGATED_BYTES = 250000000;

	/**
	 * @throws ConfigException when $wgFileImporterAccountForSuppressedUsername is invalid
	 */
	public function __construct(
		HttpApiLookup $httpApiLookup,
		HttpRequestExecutor $httpRequestExecutor,
		int $maxBytes,
		?LoggerInterface $logger = null
	) {
		$this->httpApiLookup = $httpApiLookup;
		$this->httpRequestExecutor = $httpRequestExecutor;
		$this->maxBytes = $maxBytes;
		$this->logger = $logger ?? new NullLogger();

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
	 * @throws ImportException e.g. when the file couldn't be found
	 */
	public function getImportDetails( SourceUrl $sourceUrl ): ImportDetails {
		$params = $this->getBaseParams( $sourceUrl );
		$params = $this->addFileRevisionsToParams( $params );
		$params = $this->addTextRevisionsToParams( $params );
		$params = $this->addTemplatesToParams( $params );
		$params = $this->addCategoriesToParams( $params );

		$requestData = $this->sendApiRequest( $sourceUrl, $params );

		if ( count( $requestData['query']['pages'] ?? [] ) !== 1 ) {
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

		// @phan-suppress-next-line PhanTypePossiblyInvalidDimOffset
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
	 * @param array[] $results Result set as returned by the API
	 * @param int $namespace
	 *
	 * @return string[]
	 */
	private function reduceTitleList( array $results, int $namespace ): array {
		$titles = [];
		foreach ( $results as $result ) {
			if ( $result['ns'] === $namespace ) {
				$titles[] = $result['title'];
			}
		}
		return $titles;
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
	): void {
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
	private function checkRevisionCount( SourceUrl $sourceUrl, array $pageInfoData ): void {
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
	private function checkMaxRevisionAggregatedBytes( array $pageInfoData ): void {
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
	 * @throws ImportException when the file is not acceptable, e.g. hidden or to big
	 */
	private function getFileRevisionsFromImageInfo( array $imageInfo, string $pageTitle ): FileRevisions {
		$revisions = [];
		foreach ( $imageInfo as $revisionInfo ) {
			if ( array_key_exists( 'filehidden', $revisionInfo ) ) {
				throw new LocalizedImportException( 'fileimporter-cantimportfilehidden' );
			}

			if ( array_key_exists( 'filemissing', $revisionInfo ) ) {
				throw new LocalizedImportException( 'fileimporter-filemissinginrevision' );
			}

			if ( array_key_exists( 'userhidden', $revisionInfo ) ) {
				$revisionInfo['user'] ??= $this->suppressedUsername;
			}

			if ( ( $revisionInfo['size'] ?? 0 ) > $this->maxBytes ) {
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
				$revisionInfo['comment'] ??=
					wfMessage( 'fileimporter-revision-removed-comment' )->plain();
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
	 */
	private function getTextRevisionsFromRevisionsInfo( array $revisionsInfo, string $pageTitle ): TextRevisions {
		$revisions = [];
		foreach ( $revisionsInfo as $revisionInfo ) {
			if ( array_key_exists( 'userhidden', $revisionInfo ) ) {
				$revisionInfo['user'] ??= $this->suppressedUsername;
			}

			if ( array_key_exists( 'texthidden', $revisionInfo ) ) {
				$revisionInfo['slots'][SlotRecord::MAIN]['content'] ??=
					wfMessage( 'fileimporter-revision-removed-text' )->plain();
			}

			if ( array_key_exists( 'commenthidden', $revisionInfo ) ) {
				$revisionInfo['comment'] ??=
					wfMessage( 'fileimporter-revision-removed-comment' )->plain();
			}

			$revisionInfo['minor'] = array_key_exists( 'minor', $revisionInfo );
			$revisionInfo['title'] = $pageTitle;
			$revisions[] = new TextRevision( $revisionInfo );
		}
		return new TextRevisions( $revisions );
	}

	private function getBaseParams( SourceUrl $sourceUrl ): array {
		return [
			'action' => 'query',
			'errorformat' => 'plaintext',
			'format' => 'json',
			'formatversion' => '2',
			'titles' => $this->parseTitleFromSourceUrl( $sourceUrl ),
			'prop' => 'info'
		];
	}

	/**
	 * Adds to params base the properties for getting Text Revisions
	 */
	private function addTextRevisionsToParams( array $params, ?string $rvContinue = null ): array {
		$params['prop'] .= ( $params['prop'] ) ? '|revisions' : 'revisions';

		if ( $rvContinue ) {
			$params['rvcontinue'] = $rvContinue;
		}

		return $params + [
			'rvlimit' => static::API_RESULT_LIMIT,
			'rvdir' => 'newer',
			'rvslots' => SlotRecord::MAIN,
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
	 */
	private function addFileRevisionsToParams( array $params, ?string $iiStart = null ): array {
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
	 */
	private function addTemplatesToParams( array $params, ?string $tlContinue = null ): array {
		$params['prop'] .= ( $params['prop'] ) ? '|templates' : 'templates';

		if ( $tlContinue ) {
			$params['tlcontinue'] = $tlContinue;
		}

		return $params + [ 'tlnamespace' => NS_TEMPLATE, 'tllimit' => static::API_RESULT_LIMIT ];
	}

	/**
	 * Adds to params base the properties for getting Categories
	 */
	private function addCategoriesToParams( array $params, ?string $clContinue = null ): array {
		$params['prop'] .= ( $params['prop'] ) ? '|categories' : 'categories';

		if ( $clContinue ) {
			$params['clcontinue'] = $clContinue;
		}

		return $params + [ 'cllimit' => static::API_RESULT_LIMIT ];
	}

}
