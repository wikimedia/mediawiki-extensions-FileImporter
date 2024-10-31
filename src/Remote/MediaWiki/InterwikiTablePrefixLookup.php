<?php

namespace FileImporter\Remote\MediaWiki;

use FileImporter\Data\SourceUrl;
use FileImporter\Exceptions\HttpRequestException;
use FileImporter\Interfaces\LinkPrefixLookup;
use FileImporter\Services\Http\HttpRequestExecutor;
use MediaWiki\Interwiki\InterwikiLookup;
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

	private InterwikiLookup $interwikiLookup;
	private HttpApiLookup $httpApiLookup;
	private HttpRequestExecutor $httpRequestExecutor;
	/** @var array<string,string>|null Array mapping full host name to interwiki prefix */
	private $interwikiTableMap = null;
	/**
	 * @var array<string,string>|null Array mapping parent domain to a representative URL. The idea
	 * is that, for example, a site matching *.wiktionary.* will have interwiki links to each
	 * language version of Wiktionary.
	 */
	private $parentDomainToUrlMap = null;
	/** @var string[] */
	private array $interWikiConfigMap;
	private LoggerInterface $logger;

	/**
	 * @param InterwikiLookup $interwikiLookup
	 * @param HttpApiLookup $httpApiLookup
	 * @param HttpRequestExecutor $httpRequestExecutor
	 * @param string[] $interWikiConfigMap
	 * @param LoggerInterface|null $logger
	 */
	public function __construct(
		InterwikiLookup $interwikiLookup,
		HttpApiLookup $httpApiLookup,
		HttpRequestExecutor $httpRequestExecutor,
		array $interWikiConfigMap = [],
		?LoggerInterface $logger = null
	) {
		$this->interwikiLookup = $interwikiLookup;
		$this->httpApiLookup = $httpApiLookup;
		$this->httpRequestExecutor = $httpRequestExecutor;
		$this->interWikiConfigMap = $interWikiConfigMap;
		$this->logger = $logger ?? new NullLogger();
	}

	/**
	 * @inheritDoc
	 * @return string Interwiki prefix or empty string on failure.
	 */
	public function getPrefix( SourceUrl $sourceUrl ): string {
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
	 */
	private function getPrefixFromLegacyConfig( string $host ): ?string {
		if ( isset( $this->interWikiConfigMap[$host] ) ) {
			$prefixes = explode( ':', $this->interWikiConfigMap[$host], 2 );
			if ( !$this->interwikiLookup->isValidInterwiki( $prefixes[0] ) ) {
				$this->logger->warning( 'Configured prefix {prefix} not valid.', [
					'host' => $host,
					'prefix' => $this->interWikiConfigMap[$host]
				] );

				return null;
			}

			return $this->interWikiConfigMap[$host];
		} else {
			$this->logger->debug( 'Host {host} not in FileImporterInterWikiMap, proceeding with lookup.', [
				'host' => $host ] );
			return null;
		}
	}

	/**
	 * Lookup host in the local interwiki table.
	 */
	private function getPrefixFromInterwikiTable( string $host ): ?string {
		$this->interwikiTableMap ??= $this->prefetchInterwikiMap();

		if ( !isset( $this->interwikiTableMap[$host] ) ) {
			$this->logger->debug(
				'Host {host} does not match any local interwiki entry.',
				[
					'host' => $host,
				]
			);
		}

		return $this->interwikiTableMap[$host] ?? null;
	}

	/**
	 * Lookup host by hopping through its parent domain's interwiki.
	 *
	 * This is an optimization for Wikimedia projects which are split into
	 * third-level subdomains by language, and often not present in the
	 * target wiki's local Interwiki table.
	 */
	private function getTwoHopPrefixThroughIntermediary( string $host ): ?string {
		$this->parentDomainToUrlMap ??= $this->prefetchParentDomainToHostMap();

		// TODO: The sub-domain-based intermediate host-guessing logic should be in its own
		// class, and pluggable.
		$parent = $this->getParentDomain( $host );
		if ( $parent && isset( $this->parentDomainToUrlMap[$parent] ) ) {
			$prefix = $this->getPrefixFromInterwikiTable( $this->parentDomainToUrlMap[$parent] );

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
	private function fetchSecondHopPrefix( string $intermediateWikiPrefix, string $host ): ?string {
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
			$intermediateWikiUrl = $intermediateWiki->getURL( '' );
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
					'errorformat' => 'plaintext',
					'format' => 'json',
					'formatversion' => 2,
					'meta' => 'siteinfo',
					'siprop' => 'interwikimap'
				]
			);

			$responseInterwikiMap = json_decode( $response->getContent(), true );
			foreach ( $responseInterwikiMap['query']['interwikimap'] ?? [] as $entry ) {
				if ( isset( $entry['url'] ) ) {
					if ( parse_url( $entry['url'], PHP_URL_HOST ) === $host ) {
						// FIXME: Currently this returns the first match, not the shortest
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
	 * @return array<string,string>
	 */
	private function prefetchInterwikiMap(): array {
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
	 * @return array<string,string>
	 */
	private function prefetchParentDomainToHostMap(): array {
		$this->interwikiTableMap ??= $this->prefetchInterwikiMap();

		$maps = [];
		foreach ( $this->interwikiTableMap as $host => $prefix ) {
			$parentDomain = $this->getParentDomain( $host );
			if ( $parentDomain ) {
				$maps[$parentDomain] = $host;
			}
		}

		return $maps;
	}

	/**
	 * @return string|null New hostname with the minor sub-*-domain removed.
	 */
	private function getParentDomain( string $host ): ?string {
		$parts = explode( '.', $host, 2 );
		// It doesn't make sense to reduce e.g. "mediawiki.org" to "org"
		if ( isset( $parts[1] ) && str_contains( $parts[1], '.' ) ) {
			return $parts[1];
		}
		return null;
	}

	/**
	 * @return bool true if $a is shorter or alphabetically before $b
	 */
	private function isSmaller( string $a, string $b ): bool {
		return strlen( $a ) < strlen( $b )
			|| ( strlen( $a ) === strlen( $b ) && strcmp( $a, $b ) < 0 );
	}

}
