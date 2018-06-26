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
		preg_match_all( '/{{(.*?)}}/s', $wikiText, $templates );

		$oldTemplates = [];
		$newTemplates = [];

		foreach ( $templates[1] as $template ) {
			$templateComponents = preg_split( '/(\s*\|)/', $template, 2, PREG_SPLIT_DELIM_CAPTURE );
			$templateOldName = $templateComponents[0];
			$templateNewName = $this->wikiTextConversions->swapTemplate( $templateOldName );

			if ( !$templateNewName ) {
				continue;
			}
			$templateComponents[0] = $templateNewName;

			array_push( $oldTemplates, $this->templatify( $template ) );
			array_push( $newTemplates, $this->templatify( implode( $templateComponents ) ) );
		}

		$wikiText = str_replace( $oldTemplates, $newTemplates, $wikiText, $count );

		$this->latestNumberOfReplacements = count( $oldTemplates );
		return $wikiText;
	}

	/**
	 * @param string $template
	 *
	 * @return string
	 */
	private function templatify( $template ) {
		return '{{' . $template . '}}';
	}

}
