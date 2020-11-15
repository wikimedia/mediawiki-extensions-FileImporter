<?php

namespace FileImporter;

use ExtensionRegistry;
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
use FileImporter\Services\WikiPageFactory;
use FileImporter\Services\WikiRevisionFactory;
use ImportableOldRevisionImporter;
use ImportableUploadRevisionImporter;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use ObjectCache;
use RequestContext;
use UploadBase;

// TODO: Alphabetize.
return [

	'FileImporterSourceSiteLocator' => function ( MediaWikiServices $services ) {
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

	'FileImporterHttpRequestExecutor' => function ( MediaWikiServices $services ) {
		$config = $services->getMainConfig();
		$maxFileSize = UploadBase::getMaxUploadSize( 'import' );
		$service = new HttpRequestExecutor(
			[
				'originalRequest' => RequestContext::getMain()->getRequest(),
				'proxy' => $config->get( 'CopyUploadProxy' ),
				'timeout' => $config->get( 'CopyUploadTimeout' ),
			],
			$maxFileSize
		);
		$service->setLogger( LoggerFactory::getInstance( 'FileImporter' ) );
		return $service;
	},

	'FileImporterCategoryExtractor' => function ( MediaWikiServices $services ) {
		return new CategoryExtractor(
			$services->getParser(),
			$services->getDBLoadBalancer()
		);
	},

	'FileImporterDuplicateFileRevisionChecker' => function ( MediaWikiServices $services ) {
		$localRepo = $services->getRepoGroup()->getLocalRepo();
		return new DuplicateFileRevisionChecker( $localRepo );
	},

	'FileImporterImporter' => function ( MediaWikiServices $services ) {
		/** @var WikiRevisionFactory $wikiRevisionFactory */
		$wikiRevisionFactory = $services->getService( 'FileImporterWikiRevisionFactory' );
		/** @var NullRevisionCreator $nullRevisionCreator */
		$nullRevisionCreator = $services->getService( 'FileImporterNullRevisionCreator' );
		/** @var HttpRequestExecutor $httpRequestExecutor */
		$httpRequestExecutor = $services->getService( 'FileImporterHttpRequestExecutor' );
		/** @var UploadBaseFactory $uploadBaseFactory */
		$uploadBaseFactory = $services->getService( 'FileImporterUploadBaseFactory' );

		$logger = LoggerFactory::getInstance( 'FileImporter' );

		// Construct custom core service objects so that we can inject our own Logger
		$uploadRevisionImporter = new ImportableUploadRevisionImporter(
			$services->getMainConfig()->get( 'EnableUploads' ),
			$logger
		);
		$uploadRevisionImporter->setNullRevisionCreation( false );

		$oldRevisionImporter = new ImportableOldRevisionImporter(
			true,
			$logger,
			$services->getDBLoadBalancer(),
			$services->getRevisionStore(),
			$services->getSlotRoleRegistry(),
			// Check for 1.36 for the WikiPageFactory as new argument
			is_callable( [ $services, 'getWikiPageFactory' ] ) ? $services->getWikiPageFactory() : null
		);

		$importer = new Importer(
			new WikiPageFactory(),
			$wikiRevisionFactory,
			$nullRevisionCreator,
			$httpRequestExecutor,
			$uploadBaseFactory,
			$oldRevisionImporter,
			$uploadRevisionImporter,
			new FileTextRevisionValidator(),
			$logger,
			$services->getStatsdDataFactory()
		);
		return $importer;
	},

	'FileImporterWikiRevisionFactory' => function ( MediaWikiServices $services ) {
		return new WikiRevisionFactory();
	},

	'FileImporterNullRevisionCreator' => function ( MediaWikiServices $services ) {
		return new NullRevisionCreator(
			$services->getRevisionStore(),
			$services->getDBLoadBalancer()
		);
	},

	'FileImporterImportPlanFactory' => function ( MediaWikiServices $services ) {
		/** @var SourceSiteLocator $sourceSiteLocator */
		$sourceSiteLocator = $services->getService( 'FileImporterSourceSiteLocator' );
		/** @var DuplicateFileRevisionChecker $duplicateFileChecker */
		$duplicateFileChecker = $services->getService( 'FileImporterDuplicateFileRevisionChecker' );
		/** @var UploadBaseFactory $uploadBaseFactory */
		$uploadBaseFactory = $services->getService( 'FileImporterUploadBaseFactory' );
		$factory = new ImportPlanFactory(
			$sourceSiteLocator,
			$duplicateFileChecker,
			$uploadBaseFactory
		);
		return $factory;
	},

	'FileImporterUploadBaseFactory' => function ( MediaWikiServices $services ) {
		return new UploadBaseFactory( LoggerFactory::getInstance( 'FileImporter' ) );
	},

	// Sites

	/**
	 * This configuration example can be used for development and is very plain and lenient!
	 * It will allow importing files form ANY mediawiki site.
	 */
	'FileImporter-Site-DefaultMediaWiki' => function ( MediaWikiServices $services ) {
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
				$logger,
				$services->getStatsdDataFactory()
			);
		}

		$site = new SourceSite(
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

		return $site;
	},

	/**
	 * This configuration example is setup to handle the wikimedia style setup.
	 * This only allows importing files from sites in the sites table.
	 */
	'FileImporter-WikimediaSitesTableSite' => function ( MediaWikiServices $services ) {
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
				$logger,
				$services->getStatsdDataFactory()
			);
		}

		$site = new SourceSite(
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

		return $site;
	},

	'FileImporterTemplateLookup' => function ( MediaWikiServices $services ) {
		return new WikidataTemplateLookup(
			$services->getMainConfig(),
			$services->getService( 'FileImporterMediaWikiSiteTableSiteLookup' ),
			$services->getService( 'FileImporterHttpRequestExecutor' ),
			LoggerFactory::getInstance( 'FileImporter' )
		);
	},

	'FileImporterSuccessCache' => function ( MediaWikiServices $services ) {
		return new SuccessCache(
			ObjectCache::getInstance( 'db-replicated' ),
			LoggerFactory::getInstance( 'FileImporter' )
		);
	},

];
