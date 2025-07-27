<?php

namespace FileImporter\Remote\MediaWiki;

use FileImporter\Data\SourceUrl;
use MediaWiki\Site\Site;
use MediaWiki\Site\SiteLookup;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Lookup that can be used to get a Site object from the locally configured sites based on the
 * hostname.
 *
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class SiteTableSiteLookup {

	/** @var array<string,string> */
	private array $hostGlobalIdMap = [];

	public function __construct(
		private readonly SiteLookup $siteLookup,
		private readonly LoggerInterface $logger = new NullLogger(),
	) {
	}

	public function getSite( SourceUrl $sourceUrl ): ?Site {
		return $this->getSiteFromHostMap( $sourceUrl->getHost() ) ??
			$this->getSiteFromSitesLoop( $sourceUrl->getHost() );
	}

	private function getSiteFromSitesLoop( string $host ): ?Site {
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

	private function getSiteFromHostMap( string $host ): ?Site {
		if ( array_key_exists( $host, $this->hostGlobalIdMap ) ) {
			return $this->siteLookup->getSite( $this->hostGlobalIdMap[$host] );
		}

		return null;
	}

	private function addSiteToHostMap( Site $site, string $host ): void {
		if ( $site->getGlobalId() ) {
			$this->hostGlobalIdMap[$host] = $site->getGlobalId();
		}
	}

}
