<?php

namespace FileImporter\Services;

use FileImporter\Data\SourceUrl;

/**
 * Base class for dedicated normalization rules that are only true for specific SourceUrls, as
 * detected by the corresponding SourceUrlChecker.
 *
 * @license GPL-2.0-or-later
 * @author Addshore
 */
interface SourceUrlNormalizer {

	/**
	 * @param SourceUrl $sourceUrl
	 *
	 * @return SourceUrl
	 */
	public function normalize( SourceUrl $sourceUrl );

}
