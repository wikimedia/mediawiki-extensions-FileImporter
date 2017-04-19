<?php

namespace FileImporter\Services;

use FileImporter\Data\SourceUrl;
use FileImporter\Exceptions\SourceUrlException;

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
			if ( $site->isSourceSiteFor( $sourceUrl ) ) {
				return $site;
			}
		}

		throw new SourceUrlException();
	}

}
