<?php

namespace FileImporter\Services;

use FileImporter\Data\SourceUrl;
use FileImporter\Remote\MediaWiki\SiteTableSiteLookup;
use FileImporter\Services\Http\HttpRequestExecutor;
use MediaWiki\Config\Config;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * FIXME: Rename the class to something like WikibaseSiteLinkLookup, and just remove all occurences
 * of "NowCommons" as well as "Template" from this code. Reasoning: Even if this code was written
 * for a very specific purpose (the NowCommons template), this class does not contain any knowledge
 * about templates, and no knowledge about a specific Wikibase installation.
 *
 * This service fetches an Item from a Wikibase instance (both specified via configuration), and
 * returns the sitelink that matches a given source URL, if such a sitelink exists. In other words:
 * It checks if the source site contains a localized version of a page, and returns it.
 *
 * @license GPL-2.0-or-later
 */
class WikidataTemplateLookup {

	private SiteTableSiteLookup $siteLookup;
	private HttpRequestExecutor $requestExecutor;
	private LoggerInterface $logger;
	/** @var string */
	private $entityEndpoint;
	/** @var string|null */
	private $nowCommonsEntityId;

	/** @var string[][] Array mapping site id and entity id to a template title name */
	private array $templateCache = [];

	public function __construct(
		Config $config,
		SiteTableSiteLookup $siteLookup,
		HttpRequestExecutor $requestExecutor,
		LoggerInterface $logger
	) {
		$this->siteLookup = $siteLookup;
		$this->requestExecutor = $requestExecutor;
		$this->logger = $logger;

		$this->entityEndpoint = $config->get( 'FileImporterWikidataEntityEndpoint' );
		$this->nowCommonsEntityId = $config->get( 'FileImporterWikidataNowCommonsEntity' ) ?: null;
	}

	/**
	 * Fetch the source wiki template title corresponding to `NowCommons`
	 *
	 * @param SourceUrl $sourceUrl Source URL, used to look up source site ID.
	 *
	 * @return string|null Local template title, without namespace prefix.
	 */
	public function fetchNowCommonsLocalTitle( SourceUrl $sourceUrl ): ?string {
		try {
			return $this->fetchLocalTemplateForSource( $this->nowCommonsEntityId, $sourceUrl );
		} catch ( RuntimeException $ex ) {
			$this->logger->error( 'Failed to fetch template mapping from Wikidata: ' .
				$ex->getMessage() );
			return null;
		}
	}

	private function fetchLocalTemplateForSource( ?string $entityId, SourceUrl $sourceUrl ): ?string {
		$sourceSite = $this->siteLookup->getSite( $sourceUrl );
		if ( !$sourceSite || !$entityId ) {
			return null;
		}

		$localPageName = $this->fetchSiteLinkPageName( $entityId, $sourceSite->getGlobalId() );
		if ( $localPageName === null ) {
			return null;
		}

		return $this->removeNamespace( $localPageName );
	}

	private function fetchSiteLinkPageName( string $entityId, string $siteId ): ?string {
		if ( isset( $this->templateCache[$siteId][$entityId] ) ) {
			return $this->templateCache[$siteId][$entityId];
		}

		$url = $this->entityEndpoint . $entityId;
		$response = $this->requestExecutor->execute( $url );
		$entityData = json_decode( $response->getContent(), true );
		$this->templateCache[$siteId][$entityId] =
			$entityData['entities'][$entityId]['sitelinks'][$siteId]['title'] ?? null;

		return $this->templateCache[$siteId][$entityId];
	}

	/**
	 * FIXME: copied from WikitextConversions, should use Title methods instead.
	 */
	private function removeNamespace( string $title ): string {
		$splitTitle = explode( ':', $title, 2 );
		return end( $splitTitle );
	}

}
