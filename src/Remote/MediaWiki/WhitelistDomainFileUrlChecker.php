<?php

namespace FileImporter\Remote\MediaWiki;

use FileImporter\Data\SourceUrl;

/**
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class WhitelistDomainFileUrlChecker extends AnyMediaWikiFileUrlChecker {

	/**
	 * @var string[]
	 */
	private $whiteListDomains;

	/**
	 * @param string[] $whitelistDomains
	 */
	public function __construct( array $whitelistDomains ) {
		$this->whiteListDomains = $whitelistDomains;
	}

	/**
	 * @inheritDoc
	 */
	public function checkSourceUrl( SourceUrl $sourceUrl ) {
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
