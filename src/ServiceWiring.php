<?php

namespace FileImporter;

use FileImporter\Generic\Services\DispatchingDetailRetriever;
use FileImporter\Generic\Services\DuplicateFileRevisionChecker;
use FileImporter\Generic\Services\HttpRequestExecutor;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use Psr\Log\LoggerInterface;
use RepoGroup;

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
