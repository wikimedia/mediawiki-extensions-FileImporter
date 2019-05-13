<?php

namespace FileImporter\Services;

use FileImporter\Data\SourceUrl;

/**
 * A normalizer for SourceUrls that are known to point to any (3rd-party) MediaWiki installation.
 *
 * @license GPL-2.0-or-later
 * @author Thiemo Kreuz
 */
class MediaWikiSourceUrlNormalizer implements SourceUrlNormalizer {

	/**
	 * @param SourceUrl $sourceUrl
	 *
	 * @return SourceUrl
	 */
	public function normalize( SourceUrl $sourceUrl ) {
		$url = $sourceUrl->getUrl();
		$url = str_replace( ' ', '_', $url );

		return new SourceUrl( $url );
	}

}
