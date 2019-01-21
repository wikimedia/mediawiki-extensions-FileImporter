<?php

namespace FileImporter;

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

];
