<?php


namespace FileImporter\Services;

use Config;
use FileImporter\Data\SourceUrl;
use FileImporter\Remote\MediaWiki\SiteTableSiteLookup;
use FileImporter\Services\Http\HttpRequestExecutor;
use Psr\Log\LoggerInterface;
use RuntimeException;

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
		if ( $sourceSite === null ) {
			return null;
		}
		$siteId = $sourceSite->getGlobalId();
		$localPageName = $this->fetchSiteLinkPageName( $entityId, $siteId );
		if ( $localPageName === null ) {
			return null;
		}

		$strippedTitle = $this->removeNamespaceFromString( $localPageName );
		return $strippedTitle;
	}

	/**
	 * @param string $entityId
	 * @param string $siteId
	 * @return string|null
	 */
	private function fetchSiteLinkPageName( $entityId, $siteId ) {
		$item = $this->getWikidataEntity( $entityId );

		return array_reduce(
			[ 'entities', $entityId, 'sitelinks', $siteId, 'title' ],
			function ( $data, $index ) {
				return $data[$index] ?? null;
			},
			$item
		);
	}

	/**
	 * @param string $entityId
	 * @return array
	 */
	private function getWikidataEntity( $entityId ) {
		$requestUrl = $this->buildEntityUrl( $entityId );
		$response = $this->requestExecutor->execute( $requestUrl );
		$content = $response->getContent();
		$data = json_decode( $content, true );
		return $data;
	}

	/**
	 * @param string $entityId
	 * @return string
	 */
	private function buildEntityUrl( $entityId ) {
		return $this->entityEndpoint . $entityId;
	}

	/**
	 * FIXME: copied from WikitextConversions
	 * @param string $title
	 *
	 * @return string
	 */
	private function removeNamespaceFromString( $title ) {
		$splitTitle = explode( ':', $title );
		return end( $splitTitle );
	}

}
