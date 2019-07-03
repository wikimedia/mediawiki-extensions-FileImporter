<?php

namespace FileImporter;

use CentralIdLookup;
use FileImporter\Remote\MediaWiki\CentralAuthTokenProvider;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;

/**
 * @codeCoverageIgnore
 */
return [

	'FileImporterMediaWikiHttpApiLookup' => function ( MediaWikiServices $services ) {
		/** @var \FileImporter\Services\Http\HttpRequestExecutor $httpRequestExecutor */
		$httpRequestExecutor = $services->getService( 'FileImporterHttpRequestExecutor' );

		$service = new Remote\MediaWiki\HttpApiLookup(
			$httpRequestExecutor
		);
		$service->setLogger( LoggerFactory::getInstance( 'FileImporter' ) );
		return $service;
	},

	'FileImporterMediaWikiSiteTableSiteLookup' => function ( MediaWikiServices $services ) {
		return new Remote\MediaWiki\SiteTableSiteLookup(
			$services->getSiteLookup(),
			LoggerFactory::getInstance( 'FileImporter' )
		);
	},

	'FileImporterMediaWikiRemoteApiRequestExecutor' => function ( MediaWikiServices $services ) {
		/** @var \FileImporter\Services\Http\HttpRequestExecutor $httpRequestExecutor */
		$httpRequestExecutor = $services->getService( 'FileImporterHttpRequestExecutor' );

		$httpApiLookup = new Remote\MediaWiki\HttpApiLookup(
			$httpRequestExecutor
		);

		$service = new Remote\MediaWiki\RemoteApiRequestExecutor(
			$httpApiLookup,
			$httpRequestExecutor,
			new CentralAuthTokenProvider(),
			CentralIdLookup::factory()
		);
		$service->setLogger( LoggerFactory::getInstance( 'FileImporter' ) );
		return $service;
	},

	'FileImporterMediaWikiRemoteApiActionExecutor' => function ( MediaWikiServices $services ) {
		/** @var \FileImporter\Remote\MediaWiki\RemoteApiRequestExecutor $remoteApiRequestExecutor */
		$remoteApiRequestExecutor = $services->getService( 'FileImporterMediaWikiRemoteApiRequestExecutor'
 );

		$service = new Remote\MediaWiki\RemoteApiActionExecutor(
			$remoteApiRequestExecutor
		);
		return $service;
	},

];
