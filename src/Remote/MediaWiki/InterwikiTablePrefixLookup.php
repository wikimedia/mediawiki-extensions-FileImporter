<?php

namespace FileImporter\Remote\MediaWiki;

use FileImporter\Data\SourceUrl;
use FileImporter\Interfaces\LinkPrefixLookup;
use MediaWiki\Interwiki\InterwikiLookup;
use MediaWiki\MediaWikiServices;
use Psr\Log\LoggerInterface;

/**
 * This LinkPrefixLookup implementation will allow interwiki references
 * from MediaWiki websites that are contained in the interwiki table.
 *
 * @license GPL-2.0-or-later
 * @author Christoph Jauera <christoph.jauera@wikimedia.de>
 */
class InterwikiTablePrefixLookup implements LinkPrefixLookup {

	/**
	 * @var InterwikiLookup
	 */
	private $interwikiLookup;

	/**
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * @var string[]
	 */
	private $interwikiTableMap = [];

	/**
	 * @param InterwikiLookup $interwikiLookup
	 * @param LoggerInterface $logger
	 */
	public function __construct(
		InterwikiLookup $interwikiLookup,
		LoggerInterface $logger
	) {
		$this->interwikiLookup = $interwikiLookup;
		$this->logger = $logger;
	}

	/**
	 * @inheritDoc
	 */
	public function getPrefix( SourceUrl $sourceUrl ) {
		// TODO: Implement a stable two level prefix retriever to get the prefix

		$host = $sourceUrl->getHost();
		$config = MediaWikiServices::getInstance()->getMainConfig();
		$interwikiConfigMap = $config->get( 'FileImporterInterWikiMap' );

		if ( !isset( $interwikiConfigMap[$host] ) ) {
			$this->logger->warning(
				'Site not in FileImporterInterWikiMap.',
				[
					'host' => $host,
				]
			);

			return $this->getPrefixFromInterwikiTable( $host );
		}

		$prefixes = explode( ':', $interwikiConfigMap[$host] );
		$firstPrefix = array_shift( $prefixes );
		if ( !$this->interwikiLookup->isValidInterwiki( $firstPrefix ) ) {
			$this->logger->warning(
				'Configured prefix not valid.',
				[
					'host' => $host,
					'siteId' => $interwikiConfigMap[$host]
				]
			);
			return '';
		}

		return $interwikiConfigMap[$host];
	}

	/**
	 * @param string $host
	 *
	 * @return string
	 */
	private function getPrefixFromInterwikiTable( $host ) {
		if ( isset( $this->interwikiTableMap[$host] ) ) {
			return $this->interwikiTableMap[$host];
		}

		// FIXME: This repeats a very similar (and similarily problematic) implementation from
		// SiteTableSiteLookup::getSiteFromSitesLoop(). Both compare the host only.
		foreach ( $this->interwikiLookup->getAllPrefixes() as $row ) {
			// This assumes all URLs in the interwiki (or sites) table are valid.
			if ( parse_url( $row['iw_url'], PHP_URL_HOST ) === $host ) {
				$prefix = $row['iw_prefix'];
				$this->interwikiTableMap[$host] = $prefix;
				return $prefix;
			}
		}

		$this->logger->warning(
			'Site not in InterwikiMap.',
			[
				'host' => $host,
			]
		);

		return '';
	}

}
