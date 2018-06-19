<?php

namespace FileImporter\Remote\MediaWiki;

use FileImporter\Data\SourceUrl;
use FileImporter\Interfaces\SourceInterWikiLookup;
use MediaWiki\Interwiki\InterwikiLookup;
use MediaWiki\MediaWikiServices;
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
	 * @var InterwikiLookup
	 */
	private $interwikiLookup;

	/**
	 * @var LoggerInterface
	 */
	private $logger;

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
		$interWikiMap = $config->get( 'FileImporterInterWikiMap' );

		if ( !isset( $interWikiMap[$host] ) ) {
			$this->logger->warning(
				'Site not in FileImporterInterWikiMap.',
				[
					'host' => $host,
				]
			);
			return '';
		}

		$prefixes = explode( ':', $interWikiMap[$host] );
		$firstPrefix = array_shift( $prefixes );
		if ( !$this->interwikiLookup->isValidInterwiki( $firstPrefix ) ) {
			$this->logger->warning(
				'Configured prefix not valid.',
				[
					'host' => $host,
					'siteId' => $interWikiMap[$host]
				]
			);
			return '';
		}

		return $interWikiMap[$host];
	}

}
