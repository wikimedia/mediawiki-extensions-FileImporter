<?php

namespace FileImporter\Remote\MediaWiki;

use FileImporter\Data\SourceUrl;
use FileImporter\Interfaces\SourceUrlChecker;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * This SourceUrlChecker implementation will allow files from mediawiki websites that are contained
 * in the sites table.
 *
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class SiteTableSourceUrlChecker implements SourceUrlChecker {
	use MediaWikiSourceUrlParser;

	public function __construct(
		private readonly SiteTableSiteLookup $siteTableSiteLookup,
		private readonly LoggerInterface $logger = new NullLogger(),
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function checkSourceUrl( SourceUrl $sourceUrl ): bool {
		$site = $this->siteTableSiteLookup->getSite( $sourceUrl );

		if ( !$site ) {
			$this->logger->error( __METHOD__ . ' failed site check for URL: ' . $sourceUrl->getUrl() );
			return false;
		}

		if ( $this->parseTitleFromSourceUrl( $sourceUrl ) === null ) {
			$this->logger->error( __METHOD__ . ' failed title check for URL: ' . $sourceUrl->getUrl() );
			return false;
		}

		return true;
	}

}
