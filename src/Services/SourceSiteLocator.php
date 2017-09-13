<?php

namespace FileImporter\Services;

use FileImporter\Data\SourceUrl;
use FileImporter\Exceptions\SourceUrlException;

/**
 * SourceSiteLocator for getting a SourceSite service which can handle a given URL.
 */
class SourceSiteLocator {

	/**
	 * @var SourceSite[]
	 */
	private $sourceSites;

	/**
	 * @param SourceSite[] $sourceSites
	 */
	public function __construct( array $sourceSites ) {
		$this->sourceSites = $sourceSites;
	}

	public function getSourceSite( SourceUrl $sourceUrl ) {
		foreach ( $this->sourceSites as $site ) {
			$normalizedUrl = $site->normalizeUrl( $sourceUrl );
			if ( $site->isSourceSiteFor( $normalizedUrl ) ) {
				return $site;
			}
		}

		throw new SourceUrlException();
	}

}
