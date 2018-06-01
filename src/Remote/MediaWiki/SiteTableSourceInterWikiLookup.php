<?php

namespace FileImporter\Remote\MediaWiki;

use FileImporter\Data\SourceUrl;
use FileImporter\Interfaces\SourceInterWikiLookup;

/**
 * This SourceInterWikiLookup implementation will allow interwiki references
 * from MediaWiki websites that are contained in the sites table.
 *
 * @license GPL-2.0-or-later
 * @author Christoph Jauera <christoph.jauera@wikimedia.de>
 */
class SiteTableSourceInterWikiLookup implements SourceInterWikiLookup {

	private $siteTableSiteLookup;

	public function __construct(
		SiteTableSiteLookup $siteTableSiteLookup
	) {
		$this->siteTableSiteLookup = $siteTableSiteLookup;
	}

	/**
	 * @inheritDoc
	 */
	public function getPrefix( SourceUrl $sourceUrl ) {
		$site = $this->siteTableSiteLookup->getSite( $sourceUrl->getParsedUrl()['host'] );
		$prefix = '';
		if ( $site === null ) {
			return $prefix;
		}

		$interWikiIds = $site->getInterwikiIds();
		if ( empty( $interWikiIds ) ) {
			return $prefix;
		}

		$prefix = array_pop( $interWikiIds );
		$langCode = $site->getLanguageCode();
		if ( $langCode === null || $langCode === '' ) {
			return $prefix;
		}

		return $prefix . ':' . $langCode;
	}

}
