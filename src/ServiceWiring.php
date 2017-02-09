<?php

namespace FileImporter;

use MediaWiki\MediaWikiServices;
use Psr\Log\LoggerInterface;

return [
	'FileImporterUrlBasedSiteLookup' => function( MediaWikiServices $services ) {
		return new UrlBasedSiteLookup( $services->getSiteLookup() );
	},
];
