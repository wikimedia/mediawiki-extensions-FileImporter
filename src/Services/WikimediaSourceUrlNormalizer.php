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
class WikimediaSourceUrlNormalizer extends MediaWikiSourceUrlNormalizer {

	/**
	 * @param SourceUrl $sourceUrl
	 *
	 * @return SourceUrl
	 */
	public function normalize( SourceUrl $sourceUrl ) {
		$parts = parent::normalize( $sourceUrl )->getParsedUrl();
		$parts['host'] = strtr( $parts['host'], [
			'.m.' => '.',
		] );
		$url = wfAssembleUrl( $parts );

		return new SourceUrl( $url );
	}

}
