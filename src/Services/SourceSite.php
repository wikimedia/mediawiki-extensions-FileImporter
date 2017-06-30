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

	public function __construct(
		SourceUrlChecker $sourceUrlChecker,
		DetailRetriever $detailRetriever,
		ImportTitleChecker $importTitleChecker
	) {
		$this->sourceUrlChecker = $sourceUrlChecker;
		$this->detailRetriever = $detailRetriever;
		$this->importTitleChecker = $importTitleChecker;
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
