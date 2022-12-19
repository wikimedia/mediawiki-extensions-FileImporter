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
use FileImporter\Services\WikiRevisionFactory;
use ImportableUploadRevisionImporter;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use RequestContext;
use UploadBase;

// TODO: Alphabetize.
return [

	'FileImporterSourceSiteLocator' => static function ( MediaWikiServices $services ) {
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

	'FileImporterHttpRequestExecutor' => static function ( MediaWikiServices $services ) {
		$config = $services->getMainConfig();
		$maxFileSize = UploadBase::getMaxUploadSize( 'import' );
		$service = new HttpRequestExecutor(
			$services->getHttpRequestFactory(),
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

	'FileImporterCategoryExtractor' => static function ( MediaWikiServices $services ) {
		return new CategoryExtractor(
			$services->getParser(),
			$services->getDBLoadBalancer(),
			$services->getLinkBatchFactory()
		);
	},

	'FileImporterDuplicateFileRevisionChecker' => static function ( MediaWikiServices $services ) {
		$localRepo = $services->getRepoGroup()->getLocalRepo();
		return new DuplicateFileRevisionChecker( $localRepo );
	},

	'FileImporterImporter' => static function ( MediaWikiServices $services ) {
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
			$services->getStatsdDataFactory()
		);
	},

	'FileImporterWikiRevisionFactory' => static function ( MediaWikiServices $services ) {
		return new WikiRevisionFactory();
	},

	'FileImporterNullRevisionCreator' => static function ( MediaWikiServices $services ) {
		return new NullRevisionCreator(
			$services->getRevisionStore(),
			$services->getDBLoadBalancer()
		);
	},

	'FileImporterImportPlanFactory' => static function ( MediaWikiServices $services ) {
		/** @var SourceSiteLocator $sourceSiteLocator */
		$sourceSiteLocator = $services->getService( 'FileImporterSourceSiteLocator' );
		/** @var DuplicateFileRevisionChecker $duplicateFileChecker */
		$duplicateFileChecker = $services->getService( 'FileImporterDuplicateFileRevisionChecker' );
		/** @var UploadBaseFactory $uploadBaseFactory */
		$uploadBaseFactory = $services->getService( 'FileImporterUploadBaseFactory' );

		return new ImportPlanFactory(
			$sourceSiteLocator,
			$duplicateFileChecker,
			$uploadBaseFactory
		);
	},

	'FileImporterUploadBaseFactory' => static function ( MediaWikiServices $services ) {
		return new UploadBaseFactory( LoggerFactory::getInstance( 'FileImporter' ) );
	},

	// Sites

	/**
	 * This configuration example can be used for development and is very plain and lenient!
	 * It will allow importing files form ANY mediawiki site.
	 */
	'FileImporter-Site-DefaultMediaWiki' => static function ( MediaWikiServices $services ) {
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
	'FileImporter-WikimediaSitesTableSite' => static function ( MediaWikiServices $services ) {
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

	'FileImporterTemplateLookup' => static function ( MediaWikiServices $services ) {
		return new WikidataTemplateLookup(
			$services->getMainConfig(),
			$services->getService( 'FileImporterMediaWikiSiteTableSiteLookup' ),
			$services->getService( 'FileImporterHttpRequestExecutor' ),
			LoggerFactory::getInstance( 'FileImporter' )
		);
	},

	'FileImporterSuccessCache' => static function ( MediaWikiServices $services ) {
		return new SuccessCache(
			$services->getMainObjectStash(),
			LoggerFactory::getInstance( 'FileImporter' )
		);
	},

];
