<?php

namespace FileImporter\MediaWiki;

use Site;
use SiteLookup;

class HostBasedSiteTableLookup {

	/**
	 * @var SiteLookup
	 */
	private $siteLookup;

	public function __construct( SiteLookup $siteLookup ) {
		$this->siteLookup = $siteLookup;
	}

	/**
	 * @param string $host e.g. en.wikipedia.org or commons.wikimedia.org
	 *
	 * @return Site|null
	 */
	public function getSite( $host ) {
		/** @var Site[] $sites */
		$sites = $this->siteLookup->getSites();
		foreach ( $sites as $site ) {
			if ( $site->getDomain() === $host ) {
				return $site;
			}
		}

		return null;
	}

}
