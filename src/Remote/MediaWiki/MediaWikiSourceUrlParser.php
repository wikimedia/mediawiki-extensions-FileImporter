<?php

namespace FileImporter\Remote\MediaWiki;

use FileImporter\Data\SourceUrl;

/**
 * @license GPL-2.0-or-later
 * @author Thiemo Kreuz
 */
trait MediaWikiSourceUrlParser {

	/**
	 * @param SourceUrl $sourceUrl
	 *
	 * @return string|null
	 */
	private function parseTitleFromSourceUrl( SourceUrl $sourceUrl ) {
		$parsed = $sourceUrl->getParsedUrl();

		$query = $parsed['query'] ?? '';
		parse_str( $query, $parameters );

		$path = $parsed['path'] ?? '';
		$lastSlash = strrpos( $path, '/' );

		if ( array_key_exists( 'title', $parameters ) ) {
			$title = $parameters['title'];
		} elseif ( $lastSlash !== false ) {
			$title = rawurldecode( substr( $path, $lastSlash + 1 ) );
		} else {
			return null;
		}

		return $title === '' ? null : $title;
	}

}
