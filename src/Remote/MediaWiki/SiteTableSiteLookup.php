<?php

namespace FileImporter\Remote\MediaWiki;

use FileImporter\Data\SourceUrl;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Site;
use SiteLookup;

/**
 * Lookup that can be used to get a Site object from the locally configured sites based on the
 * hostname.
 *
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class SiteTableSiteLookup {

	/**
	 * @var SiteLookup
	 */
	private $siteLookup;

	/**
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * @var string[]
	 */
	private $hostGlobalIdMap = [];

	/**
	 * @param SiteLookup $siteLookup
	 * @param LoggerInterface|null $logger
	 */
	public function __construct( SiteLookup $siteLookup, LoggerInterface $logger = null ) {
		$this->siteLookup = $siteLookup;
		$this->logger = $logger ?: new NullLogger();
	}

	/**
	 * @param SourceUrl $sourceUrl
	 *
	 * @return Site|null
	 */
	public function getSite( SourceUrl $sourceUrl ) {
		return $this->getSiteFromHostMap( $sourceUrl->getHost() ) ??
			$this->getSiteFromSitesLoop( $sourceUrl->getHost() );
	}

	/**
	 * @param string $host
	 *
	 * @return Site|null
	 */
	private function getSiteFromSitesLoop( $host ) {
		/** @var Site|null $siteFound */
		$siteFound = null;

		/** @var Site $site */
		foreach ( $this->siteLookup->getSites() as $site ) {
			if ( $site->getDomain() === $host ) {
				if ( $siteFound ) {
					$this->logger->warning(
						'Host {host} matches at least two sites, {site1} and {site2}.',
						[
							'host' => $host,
							'site1' => $siteFound->getGlobalId(),
							'site2' => $site->getGlobalId(),
						]
					);
					return null;
				}

				$siteFound = $site;
			}
		}

		if ( $siteFound ) {
			$this->addSiteToHostMap( $siteFound, $host );
			return $siteFound;
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
