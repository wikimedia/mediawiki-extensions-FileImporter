<?php

namespace FileImporter\Remote\MediaWiki;

use FileImporter\Data\SourceUrl;

/**
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class AllowedDomainsFileUrlChecker extends AnyMediaWikiFileUrlChecker {

	/** @var string[] */
	private array $allowedDomains;

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
			if ( $host === $allowedDomain ||
				// If the allowed domain starts with a . allow subdomains
				( str_starts_with( $allowedDomain, '.' ) && str_ends_with( $host, $allowedDomain ) )
			) {
				return parent::checkSourceUrl( $sourceUrl );
			}
		}

		return false;
	}

}
