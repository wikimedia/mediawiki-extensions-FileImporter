<?php

namespace FileImporter;

use FileImporter\Services\DuplicateFileRevisionChecker;
use FileImporter\Services\HttpRequestExecutor;
use FileImporter\Services\Importer;
use FileImporter\Services\NullRevisionCreator;
use FileImporter\Services\SourceSiteLocator;
use FileImporter\Services\WikiRevisionFactory;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
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

		$service = new Remote\MediaWiki\HttpApiLookup(
			$httpRequestExecutor
		);
		$service->setLogger( LoggerFactory::getInstance( 'FileImporter' ) );
		return $service;
	},

	'FileImporterMediaWikiSiteTableSiteLookup' => function ( MediaWikiServices $services ) {
		return new Remote\MediaWiki\SiteTableSiteLookup( $services->getSiteLookup() );
	},

];
