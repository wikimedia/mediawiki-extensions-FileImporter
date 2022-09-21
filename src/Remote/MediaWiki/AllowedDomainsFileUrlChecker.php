<?php

namespace FileImporter\Remote\MediaWiki;

use FileImporter\Data\SourceUrl;

/**
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class AllowedDomainsFileUrlChecker extends AnyMediaWikiFileUrlChecker {

	/**
	 * @var string[]
	 */
	private $allowedDomains;

	/**
	 * @param string[] $allowedDomains
	 */
	public function __construct( array $allowedDomains ) {
		$this->allowedDomains = $allowedDomains;
	}

	/**
	 * @inheritDoc
	 */
	public function checkSourceUrl( SourceUrl $sourceUrl ): bool {
		$host = $sourceUrl->getHost();

		foreach ( $this->allowedDomains as $allowedDomain ) {
			if ( str_starts_with( $allowedDomain, '.' ) ) {
				// If the allowed domain starts with a . allow subdomains
				if ( str_ends_with( $host, $allowedDomain ) ) {
					return parent::checkSourceUrl( $sourceUrl );
				}
			} else {
				// If there is no starting . do not allow subdomains
				if ( $host === $allowedDomain ) {
					return parent::checkSourceUrl( $sourceUrl );
				}
			}

		}

		return false;
	}

}
