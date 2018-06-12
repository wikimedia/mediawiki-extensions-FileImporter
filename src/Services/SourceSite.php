<?php

namespace FileImporter\Services;

use FileImporter\Data\ImportDetails;
use FileImporter\Data\SourceUrl;
use FileImporter\Interfaces\DetailRetriever;
use FileImporter\Interfaces\ImportTitleChecker;
use FileImporter\Interfaces\SourceUrlChecker;
use FileImporter\Interfaces\SourceInterWikiLookup;

/**
 * A SourceSite object is composed of services which can import files from configurable URLs. The
 * SourceUrlChecker provided via the constructor dictates which SourceUrls are going to be processed
 * by this service.
 *
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class SourceSite {

	private $sourceUrlChecker;
	private $detailRetriever;
	private $importTitleChecker;
	private $sourceUrlNormalizer;
	private $sourceInterWikiLookup;

	public function __construct(
		SourceUrlChecker $sourceUrlChecker,
		DetailRetriever $detailRetriever,
		ImportTitleChecker $importTitleChecker,
		SourceUrlNormalizer $sourceUrlNormalizer,
		SourceInterWikiLookup $sourceInterWikiLookup
	) {
		$this->sourceUrlChecker = $sourceUrlChecker;
		$this->detailRetriever = $detailRetriever;
		$this->importTitleChecker = $importTitleChecker;
		$this->sourceUrlNormalizer = $sourceUrlNormalizer;
		$this->sourceInterWikiLookup = $sourceInterWikiLookup;
	}

	/**
	 * @param SourceUrl $sourceUrl
	 *
	 * @return bool is this the source site for the given URL
	 */
	public function isSourceSiteFor( SourceUrl $sourceUrl ) {
		$sourceUrl = $this->sourceUrlNormalizer->normalize( $sourceUrl );
		return $this->sourceUrlChecker->checkSourceUrl( $sourceUrl );
	}

	/**
	 * @param SourceUrl $sourceUrl
	 *
	 * @return string
	 */
	public function getSitePrefix( SourceUrl $sourceUrl ) {
		return $this->sourceInterWikiLookup->getPrefix( $sourceUrl );
	}

	/**
	 * @param SourceUrl $sourceUrl
	 *
	 * @return ImportDetails
	 */
	public function retrieveImportDetails( SourceUrl $sourceUrl ) {
		$sourceUrl = $this->sourceUrlNormalizer->normalize( $sourceUrl );
		return $this->detailRetriever->getImportDetails( $sourceUrl );
	}

	/**
	 * @return ImportTitleChecker
	 */
	public function getImportTitleChecker() {
		return $this->importTitleChecker;
	}

}
