<?php

namespace FileImporter\MediaWiki;

use Site;
use SiteLookup;

class SiteTableSiteLookup {

	/**
	 * @var SiteLookup
	 */
	private $siteLookup;

	/**
	 * @var array
	 */
	private $hostGlobalIdMap = [];

	public function __construct( SiteLookup $siteLookup ) {
		$this->siteLookup = $siteLookup;
	}

	/**
	 * @param string $host e.g. en.wikipedia.org or commons.wikimedia.org
	 *
	 * @return Site|null
	 */
	public function getSite( $host ) {
		$site = $this->getSiteFromHostMap( $host );
		if ( !$site ) {
			$site = $this->getSiteFromSitesLoop( $host );
		}

		return $site;
	}

	/**
	 * @param string $host
	 *
	 * @return Site|null
	 */
	private function getSiteFromSitesLoop( $host ) {
		/** @var Site[] $sites */
		$sites = $this->siteLookup->getSites();
		foreach ( $sites as $site ) {
			if ( $site->getDomain() === $host ) {
				$this->addSiteToHostMap( $site, $host );
				return $site;
			}
		}

		return null;
	}

	/**
	 * @param string $host
	 *
	 * @return Site|null
	 */
	private function getSiteFromHostMap( $host ) {
		if ( array_key_exists( $host, $this->hostGlobalIdMap ) ) {
			return $this->siteLookup->getSite( $this->hostGlobalIdMap[$host] );
		}

		return null;
	}

	/**
	 * @param Site $site
	 * @param string $host
	 */
	private function addSiteToHostMap( Site $site, $host ) {
		if ( $site->getGlobalId() ) {
			$this->hostGlobalIdMap[$host] = $site->getGlobalId();
		}
	}

}
