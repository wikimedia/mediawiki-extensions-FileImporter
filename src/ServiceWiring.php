<?php

namespace FileImporter;

use FileImporter\Remote\MediaWiki\AnyMediaWikiFileUrlChecker;
use FileImporter\Remote\MediaWiki\ApiDetailRetriever;
use FileImporter\Remote\MediaWiki\HttpApiLookup;
use FileImporter\Remote\MediaWiki\InterwikiTablePrefixLookup;
use FileImporter\Remote\MediaWiki\RemoteApiActionExecutor;
use FileImporter\Remote\MediaWiki\RemoteApiImportTitleChecker;
use FileImporter\Remote\MediaWiki\RemoteSourceFileEditDeleteAction;
use FileImporter\Remote\MediaWiki\SiteTableSiteLookup;
use FileImporter\Remote\MediaWiki\SiteTableSourceUrlChecker;
use FileImporter\Remote\MediaWiki\SuggestManualTemplateAction;
use FileImporter\Remote\NullPrefixLookup;
use FileImporter\Services\CategoryExtractor;
use FileImporter\Services\DuplicateFileRevisionChecker;
use FileImporter\Services\FileTextRevisionValidator;
use FileImporter\Services\Http\HttpRequestExecutor;
use FileImporter\Services\Importer;
use FileImporter\Services\ImportPlanFactory;
use FileImporter\Services\NullRevisionCreator;
use FileImporter\Services\SourceSite;
use FileImporter\Services\SourceSiteLocator;
use FileImporter\Services\SuccessCache;
use FileImporter\Services\UploadBase\UploadBaseFactory;
use FileImporter\Services\WikidataTemplateLookup;
use FileImporter\Services\WikimediaSourceUrlNormalizer;
use FileImporter\Services\WikiRevisionFactory;
use FileImporter\Services\Wikitext\WikiLinkParserFactory;
use ImportableUploadRevisionImporter;
use MediaWiki\Context\RequestContext;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MainConfigNames;
use MediaWiki\MediaWikiServices;
use MediaWiki\Registration\ExtensionRegistry;
use UploadBase;

/** @phpcs-require-sorted-array */
return [

	// Sites

	/**
	 * This configuration example can be used for development and is very plain and lenient!
	 * It will allow importing files form ANY mediawiki site.
	 */
	'FileImporter-Site-DefaultMediaWiki' => static function ( MediaWikiServices $services ): SourceSite {
		/** @var HttpApiLookup $httpApiLookup */
		$httpApiLookup = $services->getService( 'FileImporterMediaWikiHttpApiLookup' );
		/** @var HttpRequestExecutor $httpRequestExecutor */
		$httpRequestExecutor = $services->getService( 'FileImporterHttpRequestExecutor' );
		$logger = LoggerFactory::getInstance( 'FileImporter' );
		$maxFileSize = UploadBase::getMaxUploadSize( 'import' );

		/** @var RemoteApiActionExecutor $remoteApiActionExecutor */
		$remoteApiActionExecutor = $services->getService(
			'FileImporterMediaWikiRemoteApiActionExecutor'
		);

		/** @var WikidataTemplateLookup $templateLookup */
		$templateLookup = $services->getService( 'FileImporterTemplateLookup' );

		$postImportHandler = new SuggestManualTemplateAction( $templateLookup );
		$config = $services->getMainConfig();
		if ( ExtensionRegistry::getInstance()->isLoaded( 'CentralAuth' ) &&
			( $config->get( 'FileImporterSourceWikiTemplating' ) ||
				$config->get( 'FileImporterSourceWikiDeletion' ) )
		) {
			$postImportHandler = new RemoteSourceFileEditDeleteAction(
				$postImportHandler,
				$templateLookup,
				$remoteApiActionExecutor,
				$services->getUrlUtils(),
				$logger,
				$services->getStatsFactory()
			);
		}

		return new SourceSite(
			new AnyMediaWikiFileUrlChecker(),
			new ApiDetailRetriever(
				$httpApiLookup,
				$httpRequestExecutor,
				$maxFileSize,
				$logger
			),
			new RemoteApiImportTitleChecker(
				$httpApiLookup,
				$httpRequestExecutor,
				$logger
			),
			new WikimediaSourceUrlNormalizer(),
			new NullPrefixLookup(),
			$postImportHandler
		);
	},

	/**
	 * This configuration example is setup to handle the wikimedia style setup.
	 * This only allows importing files from sites in the sites table.
	 */
	'FileImporter-WikimediaSitesTableSite' => static function ( MediaWikiServices $services ): SourceSite {
		/** @var SiteTableSiteLookup $siteTableLookup */
		$siteTableLookup = $services->getService( 'FileImporterMediaWikiSiteTableSiteLookup' );
		/** @var HttpApiLookup $httpApiLookup */
		$httpApiLookup = $services->getService( 'FileImporterMediaWikiHttpApiLookup' );
		/** @var HttpRequestExecutor $httpRequestExecutor */
		$httpRequestExecutor = $services->getService( 'FileImporterHttpRequestExecutor' );
		$logger = LoggerFactory::getInstance( 'FileImporter' );
		$maxFileSize = UploadBase::getMaxUploadSize( 'import' );

		/** @var RemoteApiActionExecutor $remoteApiActionExecutor */
		$remoteApiActionExecutor = $services->getService(
			'FileImporterMediaWikiRemoteApiActionExecutor'
		);

		/** @var WikidataTemplateLookup $templateLookup */
		$templateLookup = $services->getService( 'FileImporterTemplateLookup' );

		$postImportHandler = new SuggestManualTemplateAction( $templateLookup );
		$config = $services->getMainConfig();
		if ( ExtensionRegistry::getInstance()->isLoaded( 'CentralAuth' ) &&
			( $config->get( 'FileImporterSourceWikiTemplating' ) ||
				$config->get( 'FileImporterSourceWikiDeletion' ) )
		) {
			$postImportHandler = new RemoteSourceFileEditDeleteAction(
				$postImportHandler,
				$templateLookup,
				$remoteApiActionExecutor,
				$services->getUrlUtils(),
				$logger,
				$services->getStatsFactory()
			);
		}

		return new SourceSite(
			new SiteTableSourceUrlChecker(
				$siteTableLookup,
				$logger
			),
			new ApiDetailRetriever(
				$httpApiLookup,
				$httpRequestExecutor,
				$maxFileSize,
				$logger
			),
			new RemoteApiImportTitleChecker(
				$httpApiLookup,
				$httpRequestExecutor,
				$logger
			),
			new WikimediaSourceUrlNormalizer(),
			new InterwikiTablePrefixLookup(
				$services->getInterwikiLookup(),
				$httpApiLookup,
				$httpRequestExecutor,
				$services->getMainConfig()->get( 'FileImporterInterWikiMap' ),
				$logger
			),
			$postImportHandler
		);
	},

	'FileImporterCategoryExtractor' => static function ( MediaWikiServices $services ): CategoryExtractor {
		return new CategoryExtractor(
			$services->getParserFactory(),
			$services->getConnectionProvider(),
			$services->getLinkBatchFactory()
		);
	},

	'FileImporterDuplicateFileRevisionChecker' => static function (
		MediaWikiServices $services
	): DuplicateFileRevisionChecker {
		$localRepo = $services->getRepoGroup()->getLocalRepo();
		return new DuplicateFileRevisionChecker( $localRepo );
	},

	'FileImporterHttpRequestExecutor' => static function ( MediaWikiServices $services ): HttpRequestExecutor {
		$config = $services->getMainConfig();
		$maxFileSize = UploadBase::getMaxUploadSize( 'import' );
		$service = new HttpRequestExecutor(
			$services->getHttpRequestFactory(),
			[
				'originalRequest' => RequestContext::getMain()->getRequest(),
				'proxy' => $config->get( MainConfigNames::CopyUploadProxy ),
				'timeout' => $config->get( MainConfigNames::CopyUploadTimeout ),
			],
			$maxFileSize
		);
		$service->setLogger( LoggerFactory::getInstance( 'FileImporter' ) );
		return $service;
	},

	'FileImporterImporter' => static function ( MediaWikiServices $services ): Importer {
		/** @var WikiRevisionFactory $wikiRevisionFactory */
		$wikiRevisionFactory = $services->getService( 'FileImporterWikiRevisionFactory' );
		/** @var NullRevisionCreator $nullRevisionCreator */
		$nullRevisionCreator = $services->getService( 'FileImporterNullRevisionCreator' );
		/** @var HttpRequestExecutor $httpRequestExecutor */
		$httpRequestExecutor = $services->getService( 'FileImporterHttpRequestExecutor' );
		/** @var UploadBaseFactory $uploadBaseFactory */
		$uploadBaseFactory = $services->getService( 'FileImporterUploadBaseFactory' );

		$uploadRevisionImporter = $services->getUploadRevisionImporter();
		// FIXME: Should be part of the UploadRevisionImporter interface or the import() method
		if ( $uploadRevisionImporter instanceof ImportableUploadRevisionImporter ) {
			$uploadRevisionImporter->setNullRevisionCreation( false );
		}

		return new Importer(
			$services->getWikiPageFactory(),
			$wikiRevisionFactory,
			$nullRevisionCreator,
			$services->getUserIdentityLookup(),
			$httpRequestExecutor,
			$uploadBaseFactory,
			$services->getOldRevisionImporter(),
			$uploadRevisionImporter,
			new FileTextRevisionValidator(),
			$services->getRestrictionStore(),
			LoggerFactory::getInstance( 'FileImporter' ),
			$services->getStatsFactory()
		);
	},

	'FileImporterImportPlanFactory' => static function ( MediaWikiServices $services ): ImportPlanFactory {
		/** @var HttpRequestExecutor $httpRequestExecutor */
		$httpRequestExecutor = $services->getService( 'FileImporterHttpRequestExecutor' );
		/** @var SourceSiteLocator $sourceSiteLocator */
		$sourceSiteLocator = $services->getService( 'FileImporterSourceSiteLocator' );
		/** @var DuplicateFileRevisionChecker $duplicateFileChecker */
		$duplicateFileChecker = $services->getService( 'FileImporterDuplicateFileRevisionChecker' );
		/** @var UploadBaseFactory $uploadBaseFactory */
		$uploadBaseFactory = $services->getService( 'FileImporterUploadBaseFactory' );

		return new ImportPlanFactory(
			$services->getMainConfig(),
			new WikiLinkParserFactory(
				$services->getTitleParser(),
				$services->getNamespaceInfo(),
				$services->getLanguageFactory()
			),
			$services->getRestrictionStore(),
			$httpRequestExecutor,
			$sourceSiteLocator,
			$duplicateFileChecker,
			$uploadBaseFactory
		);
	},

	'FileImporterNullRevisionCreator' => static function ( MediaWikiServices $services ): NullRevisionCreator {
		return new NullRevisionCreator(
			$services->getRevisionStore(),
			$services->getConnectionProvider()
		);
	},

	'FileImporterSourceSiteLocator' => static function ( MediaWikiServices $services ): SourceSiteLocator {
		$sourceSiteServices = $services->getMainConfig()->get( 'FileImporterSourceSiteServices' );
		$sourceSites = [];

		foreach ( $sourceSiteServices as $serviceName ) {
			$sourceSites[] = $services->getService( $serviceName );
		}

		if ( $sourceSites === [] ) {
			$sourceSites[] = $services->getService( 'FileImporter-Site-DefaultMediaWiki' );
		}

		return new SourceSiteLocator( $sourceSites );
	},

	'FileImporterSuccessCache' => static function ( MediaWikiServices $services ): SuccessCache {
		return new SuccessCache(
			$services->getMainObjectStash(),
			LoggerFactory::getInstance( 'FileImporter' )
		);
	},

	'FileImporterTemplateLookup' => static function ( MediaWikiServices $services ): WikidataTemplateLookup {
		return new WikidataTemplateLookup(
			$services->getMainConfig(),
			$services->getService( 'FileImporterMediaWikiSiteTableSiteLookup' ),
			$services->getService( 'FileImporterHttpRequestExecutor' ),
			LoggerFactory::getInstance( 'FileImporter' )
		);
	},

	'FileImporterUploadBaseFactory' => static function ( MediaWikiServices $services ): UploadBaseFactory {
		return new UploadBaseFactory( LoggerFactory::getInstance( 'FileImporter' ) );
	},

	'FileImporterWikiRevisionFactory' => static function ( MediaWikiServices $services ): WikiRevisionFactory {
		return new WikiRevisionFactory(
			$services->getContentHandlerFactory()
		);
	},

];
