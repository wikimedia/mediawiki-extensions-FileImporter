<?php

namespace FileImporter\Services;

use FileImporter\Data\SourceUrl;
use FileImporter\Interfaces\DetailRetriever;
use FileImporter\Interfaces\ImportTitleChecker;
use FileImporter\Interfaces\SourceUrlChecker;

/**
 * A SourceSite object is composed of services which can import files from configurable URLs.
 */
class SourceSite {

	private $sourceUrlChecker;
	private $detailRetriever;
	private $importTitleChecker;
	private $sourceUrlNormalizer;

	public function __construct(
		SourceUrlChecker $sourceUrlChecker,
		DetailRetriever $detailRetriever,
		ImportTitleChecker $importTitleChecker,
		SourceUrlNormalizer $sourceUrlNormalizer
	) {
		$this->sourceUrlChecker = $sourceUrlChecker;
		$this->detailRetriever = $detailRetriever;
		$this->importTitleChecker = $importTitleChecker;
		$this->sourceUrlNormalizer = $sourceUrlNormalizer;
	}

	/**
	 * @param SourceUrl $sourceUrl
	 *
	 * @return bool is this the source side for the given URL
	 */
	public function isSourceSiteFor( SourceUrl $sourceUrl ) {
		return $this->sourceUrlChecker->checkSourceUrl( $sourceUrl );
	}

	/**
	 * @param SourceUrl $sourceUrl
	 *
	 * @return SourceUrl
	 */
	public function normalizeUrl( SourceUrl $sourceUrl ) {
		return $this->sourceUrlNormalizer->normalize( $sourceUrl );
	}

	/**
	 * @return DetailRetriever
	 */
	public function getDetailRetriever() {
		return $this->detailRetriever;
	}

	/**
	 * @return ImportTitleChecker
	 */
	public function getImportTitleChecker() {
		return $this->importTitleChecker;
	}

}
