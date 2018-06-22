<?php

namespace FileImporter\Services;

use FileImporter\Data\WikiTextConversions;
use FileImporter\Data\TextRevision;

/**
 * @license GPL-2.0-or-later
 */
class WikiTextContentCleaner {

	/**
	 * @var WikiTextConversions
	 */
	private $wikiTextConversions;

	public function __construct( WikiTextConversions $wikiTextConversions ) {
		$this->wikiTextConversions = $wikiTextConversions;
	}

	/**
	 * @param TextRevision $latestTextRevision
	 *
	 * @return int
	 */
	public function cleanWikiText( $latestTextRevision ) {
		$wikiText = $latestTextRevision->getField( '*' );

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
		$latestTextRevision->setField( '*', $wikiText );

		return count( $oldTemplates );
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
