<?php

namespace FileImporter\Services;

use Config;
use FileImporter\Data\SourceUrl;
use FileImporter\Remote\MediaWiki\SiteTableSiteLookup;
use FileImporter\Services\Http\HttpRequestExecutor;
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
 */
class WikidataTemplateLookup {

	/** @var SiteTableSiteLookup */
	private $siteLookup;

	/** @var HttpRequestExecutor */
	private $requestExecutor;

	/** @var LoggerInterface */
	private $logger;

	/** @var string */
	private $entityEndpoint;

	/** @var string */
	private $nowCommonsEntityId;

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
		$this->nowCommonsEntityId = $config->get( 'FileImporterWikidataNowCommonsEntity' );
	}

	/**
	 * Fetch the source wiki template title corresponding to `NowCommons`
	 *
	 * @param SourceUrl $sourceUrl Source URL, used to look up source site ID.
	 *
	 * @return string|null Local template title, without namespace prefix.
	 */
	public function fetchNowCommonsLocalTitle( SourceUrl $sourceUrl ) {
		try {
			return $this->fetchLocalTemplateForSource( $this->nowCommonsEntityId, $sourceUrl );
		} catch ( RuntimeException $ex ) {
			$this->logger->error( 'Failed to fetch template mapping from Wikidata: ' .
				$ex->getMessage() );
			return null;
		}
	}

	/**
	 * @param string $entityId
	 * @param SourceUrl $sourceUrl
	 * @return string|null
	 */
	private function fetchLocalTemplateForSource( $entityId, SourceUrl $sourceUrl ) {
		$sourceSite = $this->siteLookup->getSite( $sourceUrl );
		if ( !$sourceSite || !$entityId ) {
			return null;
		}

		$localPageName = $this->fetchSiteLinkPageName( $entityId, $sourceSite->getGlobalId() );
		if ( $localPageName === null ) {
			return null;
		}

		return $this->removePrefixes( $localPageName );
	}

	/**
	 * @param string $entityId
	 * @param string $siteId
	 * @return string|null
	 */
	private function fetchSiteLinkPageName( $entityId, $siteId ) {
		$url = $this->entityEndpoint . $entityId;
		$response = $this->requestExecutor->execute( $url );
		$entityData = json_decode( $response->getContent(), true );
		return $entityData['entities'][$entityId]['sitelinks'][$siteId]['title'] ?? null;
	}

	/**
	 * FIXME: copied from WikitextConversions
	 * @param string $title
	 *
	 * @return string
	 */
	private function removePrefixes( $title ) {
		$splitTitle = explode( ':', $title );
		return end( $splitTitle );
	}

}
