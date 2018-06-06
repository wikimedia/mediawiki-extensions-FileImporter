<?php

namespace FileImporter\Remote\MediaWiki;

use FileImporter\Data\SourceUrl;
use FileImporter\Interfaces\SourceInterWikiLookup;
use Psr\Log\LoggerInterface;

/**
 * This SourceInterWikiLookup implementation will allow interwiki references
 * from MediaWiki websites that are contained in the sites table.
 *
 * @license GPL-2.0-or-later
 * @author Christoph Jauera <christoph.jauera@wikimedia.de>
 */
class SiteTableSourceInterWikiLookup implements SourceInterWikiLookup {

	/**
	 * @var SiteTableSiteLookup
	 */
	private $siteTableSiteLookup;

	/**
	 * @var LoggerInterface
	 */
	private $logger;

	public function __construct(
		SiteTableSiteLookup $siteTableSiteLookup,
		LoggerInterface $logger
	) {
		$this->siteTableSiteLookup = $siteTableSiteLookup;
		$this->logger = $logger;
	}

	/**
	 * @inheritDoc
	 */
	public function getPrefix( SourceUrl $sourceUrl ) {
		$host = $sourceUrl->getHost();
		$site = $this->siteTableSiteLookup->getSite( $host );
		$prefix = '';
		if ( $site === null ) {
			$this->logger->warning(
				'No site found in site table.',
				[
					'host' => $host
				]
			);
			return $prefix;
		}

		$interWikiIds = $site->getInterwikiIds();
		if ( empty( $interWikiIds ) ) {
			$this->logger->warning(
				'No interWikiIds for site.',
				[
					'host' => $host,
					'siteId' => $site->getGlobalId()
				]
			);
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
