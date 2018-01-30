<?php

namespace FileImporter\Remote\MediaWiki;

use FileImporter\Data\SourceUrl;

class WhitelistDomainFileUrlChecker extends AnyMediaWikiFileUrlChecker {

	/**
	 * @var array
	 */
	private $whiteListDomains;

	public function __construct( array $whitelistDomains ) {
		$this->whiteListDomains = $whitelistDomains;
	}

	public function checkSourceUrl( SourceUrl $sourceUrl ) {
		if ( !$sourceUrl->isParsable() ) {
			return false;
		}

		$parsedUrl = $sourceUrl->getParsedUrl();
		foreach ( $this->whiteListDomains as $whiteListDomain ) {
			$whiteListDomainLength = strlen( $whiteListDomain );
			$whiteListFirstChar = $whiteListDomain[0];

			if ( $whiteListFirstChar === '.' ) {
				// If the whitelist domain starts with a . allow subdomains
				if ( substr( $parsedUrl['host'], -$whiteListDomainLength ) === $whiteListDomain ) {
					return parent::checkSourceUrl( $sourceUrl );
				}
			} else {
				// If there is no starting . do not allow subdomains
				if ( $parsedUrl['host'] === $whiteListDomain ) {
					return parent::checkSourceUrl( $sourceUrl );
				}
			}

		}

		return false;
	}

}
