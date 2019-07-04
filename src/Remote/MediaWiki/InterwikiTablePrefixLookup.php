<?php

namespace FileImporter\Remote\MediaWiki;

use Config;
use FileImporter\Data\SourceUrl;
use FileImporter\Interfaces\LinkPrefixLookup;
use FileImporter\Services\Http\HttpRequestExecutor;
use MediaWiki\Interwiki\InterwikiLookup;
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
	 * @var HttpApiLookup
	 */
	private $httpApiLookup;

	/**
	 * @var HttpRequestExecutor
	 */
	private $httpRequestExecutor;

	/**
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * @var string[] Array mapping full host name to interwiki prefix
	 */
	private $interwikiTableMap;

	/**
	 * @var Config
	 */
	private $config;

	/**
	 * @param InterwikiLookup $interwikiLookup
	 * @param HttpApiLookup $httpApiLookup
	 * @param HttpRequestExecutor $httpRequestExecutor
	 * @param LoggerInterface|null $logger
	 * @param Config $config
	 */
	public function __construct(
		InterwikiLookup $interwikiLookup,
		HttpApiLookup $httpApiLookup,
		HttpRequestExecutor $httpRequestExecutor,
		LoggerInterface $logger,
		Config $config
	) {
		$this->interwikiLookup = $interwikiLookup;
		$this->httpApiLookup = $httpApiLookup;
		$this->httpRequestExecutor = $httpRequestExecutor;
		$this->logger = $logger;
		$this->config = $config;
	}

	/**
	 * @inheritDoc
	 * @return string Interwiki prefix or empty string on failure.
	 */
	public function getPrefix( SourceUrl $sourceUrl ) {
		// TODO: Implement a stable two level prefix retriever to get the prefix

		$host = $sourceUrl->getHost();

		return $this->getPrefixFromLegacyConfig( $host );
	}

	/**
	 * Lookup the host in hardcoded configuration.
	 *
	 * @deprecated This configuration will go away once the dynamic lookup is in place.
	 * @param string $host
	 * @return string
	 */
	private function getPrefixFromLegacyConfig( $host ) {
		$interwikiConfigMap = $this->config->get( 'FileImporterInterWikiMap' );

		if ( !isset( $interwikiConfigMap[$host] ) ) {
			$this->logger->warning(
				'Host {host} not in FileImporterInterWikiMap.',
				[
					'host' => $host,
				]
			);
			return $this->getPrefixFromInterwikiTable( $host );
		}

		$prefixes = explode( ':', $interwikiConfigMap[$host], 2 );
		if ( !$this->interwikiLookup->isValidInterwiki( $prefixes[0] ) ) {
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
	 * Lookup host in the local interwiki table.
	 *
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
				// FIXME: This is noisy and useless in production.
				$this->logger->debug(
					'Skipping host {host} because it matches more than one interwiki URL: {url1} and {url2}.',
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
