<?php

namespace FileImporter;

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
		static function ( MediaWikiServices $services ): HttpApiLookup {
			/** @var HttpRequestExecutor $httpRequestExecutor */
			$httpRequestExecutor = $services->getService( 'FileImporterHttpRequestExecutor' );

			$service = new HttpApiLookup( $httpRequestExecutor );
			$service->setLogger( LoggerFactory::getInstance( 'FileImporter' ) );
			return $service;
		},

	'FileImporterMediaWikiSiteTableSiteLookup' =>
		static function ( MediaWikiServices $services ): SiteTableSiteLookup {
			return new SiteTableSiteLookup(
				$services->getSiteLookup(),
				LoggerFactory::getInstance( 'FileImporter' )
			);
		},

	'FileImporterMediaWikiRemoteApiRequestExecutor' =>
		static function ( MediaWikiServices $services ): RemoteApiRequestExecutor {
			/** @var HttpRequestExecutor $httpRequestExecutor */
			$httpRequestExecutor = $services->getService( 'FileImporterHttpRequestExecutor' );

			$service = new RemoteApiRequestExecutor(
				new HttpApiLookup( $httpRequestExecutor ),
				$httpRequestExecutor,
				new CentralAuthTokenProvider(),
				$services->getCentralIdLookupFactory()->getLookup()
			);
			$service->setLogger( LoggerFactory::getInstance( 'FileImporter' ) );
			return $service;
		},

	'FileImporterMediaWikiRemoteApiActionExecutor' =>
		static function ( MediaWikiServices $services ): RemoteApiActionExecutor {
			/** @var RemoteApiRequestExecutor $remoteApiRequestExecutor */
			$remoteApiRequestExecutor = $services->getService( 'FileImporterMediaWikiRemoteApiRequestExecutor' );

			return new RemoteApiActionExecutor( $remoteApiRequestExecutor );
		},

];
