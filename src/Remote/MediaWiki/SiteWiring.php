<?php

namespace FileImporter;

use FileImporter\Remote\MediaWiki\AnyMediaWikiFileUrlChecker;
use FileImporter\Remote\MediaWiki\SiteTableSiteLookup;
use FileImporter\Remote\MediaWiki\SiteTableSourceUrlChecker;
use FileImporter\Services\HttpRequestExecutor;
use FileImporter\Services\SourceSite;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;

return [

	// General services

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

	// SourceSite services

	/**
	 * This SourceSite service allows importing from remote MediaWiki sites that are defined
	 * in the local wikis sites table.
	 */
	'FileImporterSitesTableMediaWikiSite' => function ( MediaWikiServices $services ) {
		/**
		 * @var SiteTableSiteLookup $siteTableLookup
		 * @var \FileImporter\Remote\MediaWiki\HttpApiLookup $httpApiLookup
		 * @var HttpRequestExecutor $httpRequestExecutor
		 */
		$siteTableLookup = $services->getService( 'FileImporterMediaWikiSiteTableSiteLookup' );
		$httpApiLookup = $services->getService( 'FileImporterMediaWikiHttpApiLookup' );
		$httpRequestExecutor = $services->getService( 'FileImporterHttpRequestExecutor' );

		$detailRetriever = new Remote\MediaWiki\ApiDetailRetriever(
			$httpApiLookup,
			$httpRequestExecutor
		);
		$detailRetriever->setLogger( LoggerFactory::getInstance( 'FileImporter' ) );

		// TODO SiteTableSourceUrlChecker here should have a logger....
		// TODO ApiImportTitleChecker here should have a logger....

		$site = new SourceSite(
			new SiteTableSourceUrlChecker( $siteTableLookup ),
			$detailRetriever,
			new Remote\MediaWiki\RemoteApiImportTitleChecker(
				$httpApiLookup,
				$httpRequestExecutor
			)
		);

		return $site;
	},

	/**
	 * This SourceSite service allows importing from any remote MediaWiki site.
	 */
	'FileImporterAnyMediaWikiSite' => function ( MediaWikiServices $services ) {
		/**
		 * @var \FileImporter\Remote\MediaWiki\HttpApiLookup $httpApiLookup
		 * @var HttpRequestExecutor $httpRequestExecutor
		 */
		$httpApiLookup = $services->getService( 'FileImporterMediaWikiHttpApiLookup' );
		$httpRequestExecutor = $services->getService( 'FileImporterHttpRequestExecutor' );

		$detailRetriever = new Remote\MediaWiki\ApiDetailRetriever(
			$httpApiLookup,
			$httpRequestExecutor
		);
		$detailRetriever->setLogger( LoggerFactory::getInstance( 'FileImporter' ) );

		// TODO ApiImportTitleChecker here should have a logger....

		$site = new SourceSite(
			new AnyMediaWikiFileUrlChecker(),
			$detailRetriever,
			new Remote\MediaWiki\RemoteApiImportTitleChecker(
				$httpApiLookup,
				$httpRequestExecutor
			)
		);

		return $site;
	}

];
