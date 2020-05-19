<?php

namespace FileImporter\Services;

use FileImporter\Data\ImportDetails;
use FileImporter\Data\SourceUrl;
use FileImporter\Interfaces\DetailRetriever;
use FileImporter\Interfaces\ImportTitleChecker;
use FileImporter\Interfaces\LinkPrefixLookup;
use FileImporter\Interfaces\PostImportHandler;
use FileImporter\Interfaces\SourceUrlChecker;

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
	private $linkPrefixLookup;
	private $postImportHandler;

	/**
	 * @param SourceUrlChecker $sourceUrlChecker
	 * @param DetailRetriever $detailRetriever
	 * @param ImportTitleChecker $importTitleChecker
	 * @param SourceUrlNormalizer $sourceUrlNormalizer
	 * @param LinkPrefixLookup $linkPrefixLookup
	 * @param PostImportHandler $postImportHandler
	 */
	public function __construct(
		SourceUrlChecker $sourceUrlChecker,
		DetailRetriever $detailRetriever,
		ImportTitleChecker $importTitleChecker,
		SourceUrlNormalizer $sourceUrlNormalizer,
		LinkPrefixLookup $linkPrefixLookup,
		PostImportHandler $postImportHandler
	) {
		$this->sourceUrlChecker = $sourceUrlChecker;
		$this->detailRetriever = $detailRetriever;
		$this->importTitleChecker = $importTitleChecker;
		$this->sourceUrlNormalizer = $sourceUrlNormalizer;
		$this->linkPrefixLookup = $linkPrefixLookup;
		$this->postImportHandler = $postImportHandler;
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
	public function getLinkPrefix( SourceUrl $sourceUrl ) {
		$sourceUrl = $this->sourceUrlNormalizer->normalize( $sourceUrl );
		return $this->linkPrefixLookup->getPrefix( $sourceUrl );
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

	/**
	 * @return PostImportHandler
	 */
	public function getPostImportHandler() {
		return $this->postImportHandler;
	}

}
