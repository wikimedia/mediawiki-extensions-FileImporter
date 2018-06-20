<?php

namespace FileImporter\Remote\MediaWiki;

use FileImporter\Data\SourceUrl;
use FileImporter\Interfaces\LinkPrefixLookup;
use MediaWiki\Interwiki\InterwikiLookup;
use MediaWiki\MediaWikiServices;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

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
	 * @var string[] Array mapping full host name to interwiki prefix
	 */
	private $interwikiTableMap = null;

	/**
	 * @param InterwikiLookup $interwikiLookup
	 * @param LoggerInterface|null $logger
	 */
	public function __construct(
		InterwikiLookup $interwikiLookup,
		LoggerInterface $logger = null
	) {
		$this->interwikiLookup = $interwikiLookup;
		$this->logger = $logger ?: new NullLogger();
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
				'Host {host} not in FileImporterInterWikiMap.',
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
				'Configured prefix {prefix} not valid.',
				[
					'host' => $host,
					'prefix' => $interwikiConfigMap[$host]
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
		if ( $this->interwikiTableMap === null ) {
			$this->interwikiTableMap = $this->prefetchInterwikiMap();
		}

		if ( isset( $this->interwikiTableMap[$host] ) ) {
			return $this->interwikiTableMap[$host];
		}

		$this->logger->warning(
			'Host {host} does not match any interwiki entry.',
			[
				'host' => $host,
			]
		);
		return '';
	}

	/**
	 * @return string[]
	 */
	private function prefetchInterwikiMap() {
		$urls = [];
		$map = [];

		foreach ( $this->interwikiLookup->getAllPrefixes() as $row ) {
			// This assumes all URLs in the interwiki (or sites) table are valid.
			$host = parse_url( $row['iw_url'], PHP_URL_HOST );

			if ( isset( $urls[$host] ) && $urls[$host] !== $row['iw_url'] ) {
				$this->logger->warning(
					'Host {host} matches at least two interwiki entries, {url1} and {url2}.',
					[
						'host' => $host,
						'url1' => $urls[$host],
						'url2' => $row['iw_url'],
					]
				);
				$map[$host] = '';
				continue;
			}

			$urls[$host] = $row['iw_url'];
			if ( !isset( $map[$host] ) || $this->isSmaller( $row['iw_prefix'], $map[$host] ) ) {
				$map[$host] = $row['iw_prefix'];
			}
		}

		return $map;
	}

	/**
	 * @param string $a
	 * @param string $b
	 *
	 * @return bool true if $a is shorter or alphabetically before $b
	 */
	private function isSmaller( $a, $b ) {
		return strlen( $a ) < strlen( $b )
			|| ( strlen( $a ) === strlen( $b ) && strcmp( $a, $b ) < 0 );
	}

}
