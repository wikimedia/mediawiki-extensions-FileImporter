<?php

namespace FileImporter\Services;

use FileImporter\Data\SourceUrl;

/**
 * A normalizer for SourceUrls that are known to point to a Wikimedia wiki. In other words: This
 * class should only encode rules that are exclusive to wikis in the Wikimedia cluster.
 *
 * @license GPL-2.0-or-later
 * @author Thiemo Kreuz
 */
class WikimediaSourceUrlNormalizer extends SourceUrlNormalizer {

	public function __construct() {
		parent::__construct( function ( SourceUrl $sourceUrl ) {
			$parts = $sourceUrl->getParsedUrl();
			$parts['host'] = strtr( $parts['host'], [
				'.m.' => '.',
				'.zero.' => '.',
			] );
			$url = wfAssembleUrl( $parts );

			// TODO: Extract this normalization that is true for all (3rd-party) MediaWiki wikis to
			// a MediaWikiSourceUrlNormalizer, and use it here.
			$url = str_replace( ' ', '_', $url );

			return new SourceUrl( $url );
		} );
	}

}
