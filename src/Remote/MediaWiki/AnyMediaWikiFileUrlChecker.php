<?php

namespace FileImporter\Remote\MediaWiki;

use FileImporter\Data\SourceUrl;
use FileImporter\Interfaces\SourceUrlChecker;

/**
 * This SourceUrlChecker implementation will allow any file from any mediawiki website.
 *
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class AnyMediaWikiFileUrlChecker implements SourceUrlChecker {
	use MediaWikiSourceUrlParser;

	/**
	 * @inheritDoc
	 */
	public function checkSourceUrl( SourceUrl $sourceUrl ): bool {
		return $this->parseTitleFromSourceUrl( $sourceUrl ) !== null;
	}

}
