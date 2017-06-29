<?php

namespace FileImporter\MediaWiki;

use FileImporter\Data\SourceUrl;
use FileImporter\Interfaces\SourceUrlChecker;

class OpenSourceUrlChecker implements SourceUrlChecker {

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
		$title = null;
		$hasQueryAndTitle = null;

		if ( array_key_exists( 'query', $parsed ) ) {
			parse_str( $parsed['query'], $bits );
			$hasQueryAndTitle = array_key_exists( 'title', $bits );
			if ( $hasQueryAndTitle && strlen( $bits['title'] ) > 0 ) {
				$title = $bits['title'];
			}
		}

		if ( !$hasQueryAndTitle && array_key_exists( 'path', $parsed ) ) {
			$bits = explode( '/', $parsed['path'] );
			if ( count( $bits ) >= 2 && !empty( $bits[count( $bits ) - 1] ) ) {
				$title = array_pop( $bits );
			}
		}

		return $title;
	}

}
