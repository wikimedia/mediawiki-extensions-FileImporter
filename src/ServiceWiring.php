<?php

namespace FileImporter;

use FileImporter\Generic\DispatchingImporter;
use FileImporter\Generic\HttpRequestExecutor;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use Psr\Log\LoggerInterface;

return [

	// Generic

	'FileImporterDispatchingImporter' => function( MediaWikiServices $services ) {
		$config = $services->getMainConfig();

		$importers = [];
		foreach ( $config->get( 'FileImporterImporterServices' ) as $serviceName ) {
			$importers[] = $services->getService( $serviceName );
		}

		return new DispatchingImporter( $importers );
	},

	'FileImporterHttpRequestExecutor' => function( MediaWikiServices $services ) {
		$service = new HttpRequestExecutor();
		$service->setLogger( LoggerFactory::getInstance( 'FileImporter' ) );
		return $service;
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

		$service = new \FileImporter\MediaWiki\ApiImporter(
			$siteTableSiteLookup,
			$httpApiLookup,
			$httpRequestExecutor
		);
		$service->setLogger( LoggerFactory::getInstance( 'FileImporter' ) );

		return $service;
	}

];
