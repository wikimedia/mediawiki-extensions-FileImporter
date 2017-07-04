<?php

namespace FileImporter;

use FileImporter\Services\DuplicateFileRevisionChecker;
use FileImporter\Services\Http\HttpRequestExecutor;
use FileImporter\Services\Importer;
use FileImporter\Services\ImportPlanFactory;
use FileImporter\Services\NullRevisionCreator;
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

		$sourceSites = [];
		foreach ( $config->get( 'FileImporterSourceSiteServices' ) as $serviceName ) {
			$sourceSites[] = $services->getService( $serviceName );
		}

		return new SourceSiteLocator( $sourceSites );
	},

	'FileImporterHttpRequestExecutor' => function ( MediaWikiServices $services ) {
		$maxFileSize = UploadBase::getMaxUploadSize( 'import' );
		$service = new HttpRequestExecutor( $maxFileSize );
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

];
