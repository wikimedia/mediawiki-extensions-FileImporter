<?php

namespace FileImporter;

use FileImporter\Data\SourceUrl;
use FileImporter\Remote\MediaWiki\AnyMediaWikiFileUrlChecker;
use FileImporter\Remote\MediaWiki\SiteTableSiteLookup;
use FileImporter\Remote\MediaWiki\SiteTableSourceUrlChecker;
use FileImporter\Services\DuplicateFileRevisionChecker;
use FileImporter\Services\Http\HttpRequestExecutor;
use FileImporter\Services\Importer;
use FileImporter\Services\ImportPlanFactory;
use FileImporter\Services\SourceSite;
use FileImporter\Services\SourceSiteLocator;
use FileImporter\Services\SourceUrlNormalizer;
use FileImporter\Services\UploadBase\UploadBaseFactory;
use FileImporter\Services\WikiRevisionFactory;
use ImportableOldRevisionImporter;
use ImportableUploadRevisionImporter;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use RepoGroup;
use UploadBase;

return [

	'FileImporterSourceSiteLocator' => function ( MediaWikiServices $services ) {
		$config = $services->getMainConfig();
		$sourceSiteServices = $config->get( 'FileImporterSourceSiteServices' );
		$sourceSites = [];

		if ( !empty( $sourceSiteServices ) ) {
			foreach ( $sourceSiteServices as $serviceName ) {
				$sourceSites[] = $services->getService( $serviceName );
			}
		} else {
			$sourceSites[] = $services->getService( 'FileImporter-Site-DefaultMediaWiki' );
		}

		return new SourceSiteLocator( $sourceSites );
	},

	'FileImporterHttpRequestExecutor' => function ( MediaWikiServices $services ) {
		$timeout = $services->getMainConfig()->get( 'CopyUploadTimeout' );
		$maxFileSize = UploadBase::getMaxUploadSize( 'import' );
		$service = new HttpRequestExecutor(
			$timeout,
			$maxFileSize
		);
		$service->setLogger( LoggerFactory::getInstance( 'FileImporter' ) );
		return $service;
	},

	'FileImporterDuplicateFileRevisionChecker' => function ( MediaWikiServices $services ) {
		$localRepo = RepoGroup::singleton()->getLocalRepo();
		return new DuplicateFileRevisionChecker( $localRepo );
	},

	'FileImporterImporter' => function ( MediaWikiServices $services ) {
		/** @var WikiRevisionFactory $wikiRevisionFactory */
		$wikiRevisionFactory = $services->getService( 'FileImporterWikiRevisionFactory' );
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
		$oldRevisionImporter = new ImportableOldRevisionImporter(
			true,
			$logger,
			$services->getDBLoadBalancer()
		);

		$importer = new Importer(
			$wikiRevisionFactory,
			$httpRequestExecutor,
			$uploadBaseFactory,
			$oldRevisionImporter,
			$uploadRevisionImporter,
			$logger
		);
		return $importer;
	},

	'FileImporterWikiRevisionFactory' => function ( MediaWikiServices $services ) {
		return new WikiRevisionFactory( $services->getMainConfig() );
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
		/**
		 * @var \FileImporter\Remote\MediaWiki\HttpApiLookup $httpApiLookup
		 * @var HttpRequestExecutor $httpRequestExecutor
		 */
		$httpApiLookup = $services->getService( 'FileImporterMediaWikiHttpApiLookup' );
		$httpRequestExecutor = $services->getService( 'FileImporterHttpRequestExecutor' );
		$logger = LoggerFactory::getInstance( 'FileImporter' );
		$maxFileSize = UploadBase::getMaxUploadSize( 'import' );

		$site = new SourceSite(
			new AnyMediaWikiFileUrlChecker(),
			new Remote\MediaWiki\ApiDetailRetriever(
				$httpApiLookup,
				$httpRequestExecutor,
				$logger,
				$maxFileSize
			),
			new Remote\MediaWiki\RemoteApiImportTitleChecker(
				$httpApiLookup,
				$httpRequestExecutor,
				$logger
			),
			new SourceUrlNormalizer( function ( SourceUrl $sourceUrl ) {
				return new SourceUrl( str_replace( '..GOAT..', '', $sourceUrl->getUrl() ) );
			} )
		);

		return $site;
	},

	/**
	 * This configuration example is setup to handle the wikimedia style setup.
	 * This only allows importing files from sites in the sites table.
	 * TODO move files on disk not over http
	 */
	'FileImporter-WikimediaSitesTableSite' => function ( MediaWikiServices $services ) {
		/**
		 * @var SiteTableSiteLookup $siteTableLookup
		 * @var \FileImporter\Remote\MediaWiki\HttpApiLookup $httpApiLookup
		 * @var \FileImporter\Services\Http\HttpRequestExecutor $httpRequestExecutor
		 */
		$siteTableLookup = $services->getService( 'FileImporterMediaWikiSiteTableSiteLookup' );
		$httpApiLookup = $services->getService( 'FileImporterMediaWikiHttpApiLookup' );
		$httpRequestExecutor = $services->getService( 'FileImporterHttpRequestExecutor' );
		$logger = LoggerFactory::getInstance( 'FileImporter' );

		$site = new SourceSite(
			new SiteTableSourceUrlChecker(
				$siteTableLookup,
				$logger
			),
			new Remote\MediaWiki\ApiDetailRetriever(
				$httpApiLookup,
				$httpRequestExecutor,
				$logger
			),
			new Remote\MediaWiki\RemoteApiImportTitleChecker(
				$httpApiLookup,
				$httpRequestExecutor,
				$logger
			),
			new SourceUrlNormalizer( function ( SourceUrl $sourceUrl ) {
				return new SourceUrl( str_replace( '.m.', '.', $sourceUrl->getUrl() ) );
			} )
		);

		return $site;
	},

];
