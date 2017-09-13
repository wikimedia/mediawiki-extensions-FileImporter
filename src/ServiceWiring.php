<?php

namespace FileImporter;

use FileImporter\Remote\MediaWiki\AnyMediaWikiFileUrlChecker;
use FileImporter\Remote\MediaWiki\SiteTableSiteLookup;
use FileImporter\Remote\MediaWiki\SiteTableSourceUrlChecker;
use FileImporter\Services\DuplicateFileRevisionChecker;
use FileImporter\Services\Http\HttpRequestExecutor;
use FileImporter\Services\Importer;
use FileImporter\Services\ImportPlanFactory;
use FileImporter\Services\NullRevisionCreator;
use FileImporter\Services\SourceSite;
use FileImporter\Services\SourceSiteLocator;
use FileImporter\Services\UploadBase\UploadBaseFactory;
use FileImporter\Services\WikiRevisionFactory;
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
		/** @var NullRevisionCreator $nullRevisionCreator */
		$nullRevisionCreator = $services->getService( 'FileImporterNullRevisionCreator' );
		/** @var HttpRequestExecutor $httpRequestExecutor */
		$httpRequestExecutor = $services->getService( 'FileImporterHttpRequestExecutor' );
		/** @var UploadBaseFactory $uploadBaseFactory */
		$uploadBaseFactory = $services->getService( 'FileImporterUploadBaseFactory' );
		$importer = new Importer(
			$wikiRevisionFactory,
			$nullRevisionCreator,
			$httpRequestExecutor,
			$uploadBaseFactory
		);
		$importer->setLogger( LoggerFactory::getInstance( 'FileImporter' ) );
		return $importer;
	},

	'FileImporterNullRevisionCreator' => function ( MediaWikiServices $services ) {
		return new NullRevisionCreator( $services->getDBLoadBalancer() );
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

		$detailRetriever = new Remote\MediaWiki\ApiDetailRetriever(
			$httpApiLookup, $httpRequestExecutor
		);
		$detailRetriever->setLogger( LoggerFactory::getInstance( 'FileImporter' ) );

		// TODO ApiImportTitleChecker here should have a logger....

		$site = new SourceSite(
			new AnyMediaWikiFileUrlChecker(),
			$detailRetriever,
			new Remote\MediaWiki\RemoteApiImportTitleChecker(
				$httpApiLookup, $httpRequestExecutor
			)
		);

		return $site;
	},

	/**
	 * This configuration example is setup to handle the wikimedia style setup.
	 * This only allows importing files from sites in the sites table.
	 * TODO move files on disk not over http
	 * TODO normalize domains such as en.m.wikipedia.org
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

		$detailRetriever = new Remote\MediaWiki\ApiDetailRetriever(
			$httpApiLookup, $httpRequestExecutor
		);
		$detailRetriever->setLogger( LoggerFactory::getInstance( 'FileImporter' ) );

		// TODO SiteTableSourceUrlChecker here should have a logger....
		// TODO ApiImportTitleChecker here should have a logger....

		$site = new SourceSite(
			new SiteTableSourceUrlChecker( $siteTableLookup ),
			$detailRetriever,
			new Remote\MediaWiki\RemoteApiImportTitleChecker(
				$httpApiLookup, $httpRequestExecutor
			)
		);

		return $site;
	},

];
