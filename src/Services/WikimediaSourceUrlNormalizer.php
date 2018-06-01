<?php

namespace FileImporter\Services;

use FileImporter\Data\SourceUrl;

/**
 * @license GPL-2.0-or-later
 * @author Thiemo Kreuz
 */
class WikimediaSourceUrlNormalizer extends SourceUrlNormalizer {

	public function __construct() {
		parent::__construct( function ( SourceUrl $sourceUrl ) {
			$parts = $sourceUrl->getParsedUrl();
			$parts['host'] = str_replace( '.m.', '.', $parts['host'] );
			return new SourceUrl( wfAssembleUrl( $parts ) );
		} );
	}

}
