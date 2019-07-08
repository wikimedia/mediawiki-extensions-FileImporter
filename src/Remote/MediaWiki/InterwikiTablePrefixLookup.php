<?php

namespace FileImporter\Remote\MediaWiki;

use Config;
use FileImporter\Data\SourceUrl;
use FileImporter\Exceptions\HttpRequestException;
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
	 * @var string[] Array mapping a base domain to a best-fit url
	 */
	private $baseDomainToUrlMap;

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
		$host = $sourceUrl->getHost();

		// TODO: Wrap this class in a caching lookup to save each successful host -> prefix mapping.

		return $this->getPrefixFromLegacyConfig( $host ) ??
			$this->getPrefixFromInterwikiTable( $host ) ??
			$this->getTwoHopPrefixThroughIntermediary( $host ) ??
			'';
	}

	/**
	 * Lookup the host in hardcoded configuration.
	 *
	 * @deprecated This configuration will go away once dynamic lookup is in place.
	 * @param string $host
	 * @return string
	 */
	private function getPrefixFromLegacyConfig( $host ) {
		$interwikiConfigMap = $this->config->get( 'FileImporterInterWikiMap' );

		if ( isset( $interwikiConfigMap[$host] ) ) {
			$prefixes = explode( ':', $interwikiConfigMap[$host], 2 );
			if ( !$this->interwikiLookup->isValidInterwiki( $prefixes[0] ) ) {
				$this->logger->warning( 'Configured prefix {prefix} not valid.', [
					'host' => $host,
					'prefix' => $interwikiConfigMap[$host]
				] );

				return null;
			}

			return $interwikiConfigMap[$host];
		} else {
			$this->logger->debug( 'Host {host} not in FileImporterInterWikiMap, proceeding with lookup.', [
				'host' => $host ] );
			return null;
		}
	}

	/**
	 * Lookup host in the local interwiki table.
	 *
	 * @param string $host
	 *
	 * @return string|null
	 */
	private function getPrefixFromInterwikiTable( $host ) {
		if ( $this->interwikiTableMap === null ) {
			$this->interwikiTableMap = $this->prefetchInterwikiMap();
		}

		if ( isset( $this->interwikiTableMap[$host] ) ) {
			return $this->interwikiTableMap[$host];
		} else {
			$this->logger->debug(
				'Host {host} does not match any local interwiki entry.',
				[
					'host' => $host,
				]
			);

			return null;
		}
	}

	/**
	 * Lookup host by hopping through its base domain's interwiki.
	 *
	 * This is an optimization for Wikimedia projects which are split into
	 * third-level subdomains by language, and often not present in the
	 * target wiki's local Interwiki table.
	 *
	 * @param string $host
	 *
	 * @return string|null
	 */
	private function getTwoHopPrefixThroughIntermediary( $host ) {
		if ( $this->baseDomainToUrlMap === null ) {
			$this->baseDomainToUrlMap = $this->prefetchBaseDomainToHostMap();
		}

		// TODO: The second-level-domain-based intermediate host-guessing logic should be in its own
		// class, and pluggable.
		list( $base, ) = $this->getBaseDomain( $host );
		if ( isset( $this->baseDomainToUrlMap[$base] ) ) {
			$prefix = $this->getPrefixFromInterwikiTable( $this->baseDomainToUrlMap[$base] );

			if ( $prefix !== null ) {
				$secondHop = $this->fetchSecondHopPrefix( $prefix, $host );
				if ( $secondHop !== null ) {
					// TODO: It would be luxurious to find the shortest matching prefix.
					$fullPrefix = $prefix . ':' . $secondHop;
					$this->logger->info( 'Calculated two-hop interwiki prefix {prefix} to {host}', [
						'host' => $host,
						'prefix' => $fullPrefix,
					] );
					return $fullPrefix;
				}
			}
		}
		return null;
	}

	/**
	 * Fetch the next interwiki prefix from the first hop's API.
	 *
	 * @param string $intermediateWikiPrefix first hop
	 * @param string $host final host
	 *
	 * @return string|null
	 */
	private function fetchSecondHopPrefix( $intermediateWikiPrefix, $host ) {
		$this->logger->debug( 'Fetching second hop to {host} via {prefix}', [
			'host' => $host,
			'prefix' => $intermediateWikiPrefix ] );
		$intermediateWiki = $this->interwikiLookup->fetch( $intermediateWikiPrefix );
		if ( !$intermediateWiki ) {
			$this->logger->warning( 'Missing interwiki entry for {prefix}', [
				'prefix' => $intermediateWikiPrefix ] );
			return null;
		}
		$intermediateWikiApiUrl = $intermediateWiki->getAPI();
		if ( $intermediateWikiApiUrl === '' ) {
			$this->logger->debug( 'Missing API URL for interwiki {prefix}, scraping from mainpage.', [
				'prefix' => $intermediateWikiPrefix ] );
			$intermediateWikiUrl = $intermediateWiki->getURL();
			$intermediateWikiApiUrl = $this->httpApiLookup->getApiUrl(
				new SourceUrl( $intermediateWikiUrl ) );
		}

		try {
			$this->logger->debug( 'Making API request to pull interwiki links from {api}.', [
				'api' => $intermediateWikiApiUrl ] );
			$response = $this->httpRequestExecutor->execute(
				$intermediateWikiApiUrl,
				[
					'action' => 'query',
					'format' => 'json',
					'meta' => 'siteinfo',
					'siprop' => 'interwikimap'
				]
			);

			$responseInterwikiMap = json_decode( $response->getContent(), true );
			foreach ( $responseInterwikiMap['query']['interwikimap'] ?? [] as $entry ) {
				if ( isset( $entry['url'] ) ) {
					if ( parse_url( $entry['url'], PHP_URL_HOST ) === $host ) {
						return $entry['prefix'];
					}
				}
			}
		} catch ( HttpRequestException $e ) {
			$this->logger->warning( 'Failed to make API request to {api}.', [
				'api' => $intermediateWikiApiUrl ] );
		}

		$this->logger->info(
			'Failed to find second interwiki hop from {api} to {host}.',
			[
				'api' => $intermediateWikiApiUrl,
				'host' => $host
			]
		);

		return null;
	}

	/**
	 * @return string[]
	 * FIXME: made public to allow test mocking :(
	 */
	public function prefetchInterwikiMap() {
		$map = [];

		foreach ( $this->interwikiLookup->getAllPrefixes() as $row ) {
			// This assumes all URLs in the interwiki (or sites) table are valid.
			$host = parse_url( $row['iw_url'], PHP_URL_HOST );

			if ( !isset( $map[$host] ) || $this->isSmaller( $row['iw_prefix'], $map[$host] ) ) {
				$map[$host] = $row['iw_prefix'];
			}
		}

		return $map;
	}

	/**
	 * @return string[]
	 */
	private function prefetchBaseDomainToHostMap() {
		if ( $this->interwikiTableMap === null ) {
			$this->interwikiTableMap = $this->prefetchInterwikiMap();
		}

		$maps = [];
		foreach ( $this->interwikiTableMap as $host => $prefix ) {
			list( $base, $hostSplit ) = $this->getBaseDomain( $host );

			if ( isset( $maps[$base] ) ) {
				if ( count( $hostSplit ) === 3 && $hostSplit[0] === 'en' ) {
					$maps[$base] = $host;
				}
			} else {
				$maps[$base] = $host;
			}
		}

		return $maps;
	}

	/**
	 * @param String $host
	 *
	 * @return array of the base domain and the exploded input
	 */
	private function getBaseDomain( $host ) {
		$hostSplit = explode( '.', $host );
		$tld = $hostSplit[count( $hostSplit ) - 1];
		$domain = $hostSplit[count( $hostSplit ) - 2];
		$base = $domain . '.' . $tld;
		return [ $base, $hostSplit ];
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
