<?php

namespace FileImporter;

use CentralIdLookup;
use FileImporter\Remote\MediaWiki\CentralAuthTokenProvider;
use FileImporter\Remote\MediaWiki\HttpApiLookup;
use FileImporter\Remote\MediaWiki\RemoteApiActionExecutor;
use FileImporter\Remote\MediaWiki\RemoteApiRequestExecutor;
use FileImporter\Remote\MediaWiki\SiteTableSiteLookup;
use FileImporter\Services\Http\HttpRequestExecutor;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;

/**
 * @codeCoverageIgnore
 */
return [

	'FileImporterMediaWikiHttpApiLookup' =>
		function ( MediaWikiServices $services ) : HttpApiLookup {
			/** @var HttpRequestExecutor $httpRequestExecutor */
			$httpRequestExecutor = $services->getService( 'FileImporterHttpRequestExecutor' );

			$service = new HttpApiLookup( $httpRequestExecutor );
			$service->setLogger( LoggerFactory::getInstance( 'FileImporter' ) );
			return $service;
		},

	'FileImporterMediaWikiSiteTableSiteLookup' =>
		function ( MediaWikiServices $services ) : SiteTableSiteLookup {
			return new SiteTableSiteLookup(
				$services->getSiteLookup(),
				LoggerFactory::getInstance( 'FileImporter' )
			);
		},

	'FileImporterMediaWikiRemoteApiRequestExecutor' =>
		function ( MediaWikiServices $services ) : RemoteApiRequestExecutor {
			/** @var HttpRequestExecutor $httpRequestExecutor */
			$httpRequestExecutor = $services->getService( 'FileImporterHttpRequestExecutor' );

			$service = new RemoteApiRequestExecutor(
				new HttpApiLookup( $httpRequestExecutor ),
				$httpRequestExecutor,
				new CentralAuthTokenProvider(),
				CentralIdLookup::factory()
			);
			$service->setLogger( LoggerFactory::getInstance( 'FileImporter' ) );
			return $service;
		},

	'FileImporterMediaWikiRemoteApiActionExecutor' =>
		function ( MediaWikiServices $services ) : RemoteApiActionExecutor {
			/** @var RemoteApiRequestExecutor $remoteApiRequestExecutor */
			$remoteApiRequestExecutor = $services->getService( 'FileImporterMediaWikiRemoteApiRequestExecutor' );

			return new RemoteApiActionExecutor( $remoteApiRequestExecutor );
		},

];
