<?php

namespace FileImporter;

use FileImporter\MediaWiki\HttpApiLookup;
use FileImporter\MediaWiki\SiteTableSiteLookup;
use FileImporter\MediaWiki\SiteTableSourceUrlChecker;
use FileImporter\Services\DuplicateFileRevisionChecker;
use FileImporter\Services\HttpRequestExecutor;
use FileImporter\Services\Importer;
use FileImporter\Services\NullRevisionCreator;
use FileImporter\Services\SourceSite;
use FileImporter\Services\SourceSiteLocator;
use FileImporter\Services\WikiRevisionFactory;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use Psr\Log\LoggerInterface;
use RepoGroup;
use UploadBase;

return [

	// Generic

	'FileImporterSourceSiteLocator' => function ( MediaWikiServices $services ) {
		$config = $services->getMainConfig();

		$sourceSites = [];
		foreach ( $config->get( 'FileImporterSourceSiteServices' ) as $serviceName ) {
			$sourceSites[] = $services->getService( $serviceName );
		}

		return new SourceSiteLocator( $sourceSites );
	},

	'FileImporterHttpRequestExecutor' => function ( MediaWikiServices $services ) {
		$service = new HttpRequestExecutor();
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
		$maxUploadSize = UploadBase::getMaxUploadSize( 'import' );
		$importer = new Importer(
			$wikiRevisionFactory,
			$nullRevisionCreator,
			$maxUploadSize
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

	// MediaWiki

	'FileImporterMediaWikiHttpApiLookup' => function ( MediaWikiServices $services ) {
		/** @var HttpRequestExecutor $httpRequestExecutor */
		$httpRequestExecutor = $services->getService( 'FileImporterHttpRequestExecutor' );

		$service = new \FileImporter\MediaWiki\HttpApiLookup(
			$httpRequestExecutor
		);
		$service->setLogger( LoggerFactory::getInstance( 'FileImporter' ) );
		return $service;
	},

	'FileImporterMediaWikiSiteTableSiteLookup' => function ( MediaWikiServices $services ) {
		return new \FileImporter\MediaWiki\SiteTableSiteLookup( $services->getSiteLookup() );
	},

	// Importers

	'FileImporterSitesTableMediaWikiSite' => function ( MediaWikiServices $services ) {
		/**
		 * @var SiteTableSiteLookup $siteTableLookup
		 * @var HttpApiLookup $httpApiLookup
		 * @var HttpRequestExecutor $httpRequestExecutor
		 */
		$siteTableLookup = $services->getService( 'FileImporterMediaWikiSiteTableSiteLookup' );
		$httpApiLookup = $services->getService( 'FileImporterMediaWikiHttpApiLookup' );
		$httpRequestExecutor = $services->getService( 'FileImporterHttpRequestExecutor' );

		$detailRetriever = new \FileImporter\MediaWiki\ApiDetailRetriever(
			$httpApiLookup,
			$httpRequestExecutor
		);
		$detailRetriever->setLogger( LoggerFactory::getInstance( 'FileImporter' ) );

		// TODO SiteTableSourceUrlChecker here should have a logger....
		// TODO ApiImportTitleChecker here should have a logger....

		$site = new SourceSite(
			new SiteTableSourceUrlChecker( $siteTableLookup ),
			$detailRetriever,
			new \FileImporter\MediaWiki\ApiImportTitleChecker(
				$httpApiLookup,
				$httpRequestExecutor
			)
		);

		return $site;
	}

];
