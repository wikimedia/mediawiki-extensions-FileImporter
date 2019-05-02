<?php

namespace FileImporter\Remote\MediaWiki;

use FileImporter\Data\SourceUrl;
use FileImporter\Interfaces\SourceUrlChecker;

/**
 * This SourceUrlChecker implementation will allow any file from any mediawiki website.
 *
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class AnyMediaWikiFileUrlChecker implements SourceUrlChecker {

	/**
	 * @inheritDoc
	 */
	public function checkSourceUrl( SourceUrl $sourceUrl ) {
		return $this->getTitleFromSourceUrl( $sourceUrl ) !== null;
	}

	/**
	 * @todo factor out into another object
	 * @param SourceUrl $sourceUrl
	 * @return string|null the string title extracted or null on failure
	 */
	private function getTitleFromSourceUrl( SourceUrl $sourceUrl ) {
		$parsed = $sourceUrl->getParsedUrl();

		if ( array_key_exists( 'query', $parsed ) ) {
			parse_str( $parsed['query'], $bits );
			if ( array_key_exists( 'title', $bits ) && strlen( $bits['title'] ) > 0 ) {
				return $bits['title'];
			}
		}

		if ( array_key_exists( 'path', $parsed ) ) {
			$bits = explode( '/', $parsed['path'] );
			if ( count( $bits ) >= 2 && end( $bits ) !== '' ) {
				return end( $bits );
			}
		}

		return null;
	}

}
