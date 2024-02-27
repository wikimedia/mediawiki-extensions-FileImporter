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

	/** @var SourceUrlChecker */
	private $sourceUrlChecker;
	/** @var DetailRetriever */
	private $detailRetriever;
	/** @var ImportTitleChecker */
	private $importTitleChecker;
	/** @var SourceUrlNormalizer */
	private $sourceUrlNormalizer;
	/** @var LinkPrefixLookup */
	private $linkPrefixLookup;
	/** @var PostImportHandler */
	private $postImportHandler;

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
	 * @return bool is this the source site for the given URL
	 */
	public function isSourceSiteFor( SourceUrl $sourceUrl ): bool {
		$sourceUrl = $this->sourceUrlNormalizer->normalize( $sourceUrl );
		return $this->sourceUrlChecker->checkSourceUrl( $sourceUrl );
	}

	public function getLinkPrefix( SourceUrl $sourceUrl ): string {
		$sourceUrl = $this->sourceUrlNormalizer->normalize( $sourceUrl );
		return $this->linkPrefixLookup->getPrefix( $sourceUrl );
	}

	public function retrieveImportDetails( SourceUrl $sourceUrl ): ImportDetails {
		$sourceUrl = $this->sourceUrlNormalizer->normalize( $sourceUrl );
		return $this->detailRetriever->getImportDetails( $sourceUrl );
	}

	public function getImportTitleChecker(): ImportTitleChecker {
		return $this->importTitleChecker;
	}

	public function getPostImportHandler(): PostImportHandler {
		return $this->postImportHandler;
	}

}
