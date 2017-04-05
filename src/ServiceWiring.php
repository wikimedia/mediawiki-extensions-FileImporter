<?php

namespace FileImporter;

use FileImporter\Generic\Services\DispatchingDetailRetriever;
use FileImporter\Generic\Services\DuplicateFileRevisionChecker;
use FileImporter\Generic\Services\HttpRequestExecutor;
use FileImporter\Generic\Services\Importer;
use FileImporter\Generic\Services\NullRevisionCreator;
use FileImporter\Generic\Services\WikiRevisionFactory;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use Psr\Log\LoggerInterface;
use RepoGroup;
use UploadBase;

return [

	// Generic

	'FileImporterDispatchingDetailRetriever' => function( MediaWikiServices $services ) {
		$config = $services->getMainConfig();

		$detailRetrievers = [];
		foreach ( $config->get( 'FileImporterDetailRetrieverServices' ) as $serviceName ) {
			$detailRetrievers[] = $services->getService( $serviceName );
		}

		return new DispatchingDetailRetriever( $detailRetrievers );
	},

	'FileImporterHttpRequestExecutor' => function( MediaWikiServices $services ) {
		$service = new HttpRequestExecutor();
		$service->setLogger( LoggerFactory::getInstance( 'FileImporter' ) );
		return $service;
	},

	'FileImporterDuplicateFileRevisionChecker' => function( MediaWikiServices $services ) {
		$localRepo = RepoGroup::singleton()->getLocalRepo();
		return new DuplicateFileRevisionChecker( $localRepo );
	},

	'FileImporterImporter' => function( MediaWikiServices $services ) {
		/** @var WikiRevisionFactory $wikiRevisionFactory */
		$wikiRevisionFactory = $services->getService( 'FileImporterWikiRevisionFactory' );
		/** @var NullRevisionCreator $nullRevisionCreator */
		$nullRevisionCreator = $services->getService( 'FileImporterNullRevisionCreator' );
		$maxUploadSize = UploadBase::getMaxUploadSize( 'import' );
		return new Importer(
			$wikiRevisionFactory,
			$nullRevisionCreator,
			$maxUploadSize
		);
	},

	'FileImporterNullRevisionCreator' => function( MediaWikiServices $services ) {
		return new NullRevisionCreator( $services->getDBLoadBalancer() );
	},

	'FileImporterWikiRevisionFactory' => function( MediaWikiServices $services ) {
		return new WikiRevisionFactory( $services->getMainConfig() );
	},

	// MediaWiki

	'FileImporterMediaWikiHttpApiLookup' => function( MediaWikiServices $services ) {
		/** @var HttpRequestExecutor $httpRequestExecutor */
		$httpRequestExecutor = $services->getService( 'FileImporterHttpRequestExecutor' );

		$service = new \FileImporter\MediaWiki\HttpApiLookup(
			$httpRequestExecutor
		);
		$service->setLogger( LoggerFactory::getInstance( 'FileImporter' ) );
		return $service;
	},

	'FileImporterMediaWikiSiteTableSiteLookup' => function( MediaWikiServices $services ) {
		return new \FileImporter\MediaWiki\SiteTableSiteLookup( $services->getSiteLookup() );
	},

	// Importers

	'FileImporterMediaWikiApiImporter' => function( MediaWikiServices $services ) {
		/**
		 * @var \FileImporter\MediaWiki\SiteTableSiteLookup $siteTableSiteLookup
		 * @var \FileImporter\MediaWiki\HttpApiLookup $httpApiLookup
		 * @var HttpRequestExecutor $httpRequestExecutor
		 */
		$siteTableSiteLookup = $services->getService( 'FileImporterMediaWikiSiteTableSiteLookup' );
		$httpApiLookup = $services->getService( 'FileImporterMediaWikiHttpApiLookup' );
		$httpRequestExecutor = $services->getService( 'FileImporterHttpRequestExecutor' );

		$service = new \FileImporter\MediaWiki\ApiDetailRetriever(
			$siteTableSiteLookup,
			$httpApiLookup,
			$httpRequestExecutor
		);
		$service->setLogger( LoggerFactory::getInstance( 'FileImporter' ) );

		return $service;
	}

];
