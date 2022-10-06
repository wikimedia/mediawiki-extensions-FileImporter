<?php

namespace FileImporter\Services;

use FileImporter\Data\SourceUrl;
use FileImporter\Exceptions\SourceUrlException;

/**
 * SourceSiteLocator for getting a SourceSite service which can handle a given URL.
 *
 * @license GPL-2.0-or-later
 * @author Addshore
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

	/**
	 * @param SourceUrl $sourceUrl
	 *
	 * @return SourceSite
	 * @throws SourceUrlException when the URL doesn't match a known site
	 */
	public function getSourceSite( SourceUrl $sourceUrl ): SourceSite {
		foreach ( $this->sourceSites as $site ) {
			if ( $site->isSourceSiteFor( $sourceUrl ) ) {
				return $site;
			}
		}

		throw new SourceUrlException();
	}

}
