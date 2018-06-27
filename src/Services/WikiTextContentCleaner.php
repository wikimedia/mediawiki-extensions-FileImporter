<?php

namespace FileImporter\Services;

use FileImporter\Data\WikiTextConversions;

/**
 * @license GPL-2.0-or-later
 */
class WikiTextContentCleaner {

	/**
	 * @var int
	 */
	private $latestNumberOfReplacements = 0;

	/**
	 * @var WikiTextConversions
	 */
	private $wikiTextConversions;

	public function __construct( WikiTextConversions $wikiTextConversions ) {
		$this->wikiTextConversions = $wikiTextConversions;
	}

	/**
	 * @return int
	 */
	public function getLatestNumberOfReplacements() {
		return $this->latestNumberOfReplacements;
	}

	/**
	 * @param string $wikiText
	 *
	 * @return string
	 */
	public function cleanWikiText( $wikiText ) {
		$this->latestNumberOfReplacements = 0;

		preg_match_all(
			// This intentionally only searches for the start of each template
			'/(?<!{){{\s*+([^{|}]+?)\s*(?=\||}})/s',
			$wikiText,
			$matches,
			PREG_OFFSET_CAPTURE
		);

		// Replacements must be applied in reverse order to not mess with the captured offsets!
		for ( $i = count( $matches[1] ); $i-- > 0; ) {
			list( $oldTemplateName, $position ) = $matches[1][$i];

			$newTemplateName = $this->wikiTextConversions->swapTemplate( $oldTemplateName );
			if ( !$newTemplateName ) {
				continue;
			}

			$wikiText = substr_replace(
				$wikiText,
				$newTemplateName,
				$position,
				strlen( $oldTemplateName )
			);
			$this->latestNumberOfReplacements++;
		}

		return $wikiText;
	}

}
