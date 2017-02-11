<?php

namespace FileImporter;

use FileImporter\Generic\DispatchingImporter;
use FileImporter\MediaWiki\MediaWikiImporter;
use FileImporter\MediaWiki\HostBasedSiteTableLookup;
use MediaWiki\MediaWikiServices;
use Psr\Log\LoggerInterface;

return [

	'FileImporterUrlBasedSiteLookup' => function( MediaWikiServices $services ) {
		return new HostBasedSiteTableLookup( $services->getSiteLookup() );
	},

	'FileImporterDispatchingImporter' => function( MediaWikiServices $services ) {
		$config = $services->getMainConfig();

		$importers = [];
		foreach ( $config->get( 'FileImporterImporterServices' ) as $serviceName ) {
			$importers[] = $services->getService( $serviceName );
		}

		return new DispatchingImporter( $importers );
	},

	'FileImporterMediaWikiImporter' => function( MediaWikiServices $services ) {
		return new MediaWikiImporter();
	}

];
