<?php

namespace FileImporter;

use Site;
use SiteLookup;

class UrlBasedSiteLookup {

	/**
	 * @var SiteLookup
	 */
	private $siteLookup;

	public function __construct( SiteLookup $siteLookup ) {
		$this->siteLookup = $siteLookup;
	}

	/**
	 * @param string[] $parsedUrl result of wfParseUrl
	 *
	 * @return Site|null
	 */
	public function getSite( array $parsedUrl ) {
		$requestedDomain = $parsedUrl['host'];

		/** @var Site[] $sites */
		$sites = $this->siteLookup->getSites();
		foreach ( $sites as $site ) {
			if ( $site->getDomain() === $requestedDomain ) {
				return $site;
			}
		}

		return null;
	}

}
