<?php

namespace FileImporter\Services\Wikitext;

/**
 * @license GPL-2.0-or-later
 * @author Thiemo Kreuz
 */
class WikiLinkParser {

	/**
	 * @var WikiLinkCleaner[]
	 */
	private $cleaners = [];

	/**
	 * @param WikiLinkCleaner $cleaner
	 */
	public function registerWikiLinkCleaner( WikiLinkCleaner $cleaner ) {
		$this->cleaners[] = $cleaner;
	}

	/**
	 * @param string $wikitext
	 *
	 * @return string
	 */
	public function parse( $wikitext ) {
		return preg_replace_callback(
			'/
				# Look-behind for the opening [[
				(?<=\[\[)
				# The extra + at the end of ++ avoids backtracking
				[^\v\[|\]]++
				# Look-ahead for | or the closing ]]
				(?=\||\]\])
			/xu',
			function ( $matches ) {
				$link = $matches[0];
				foreach ( $this->cleaners as $cleaner ) {
					$link = $cleaner->process( $link );
				}
				return $link;
			},
			$wikitext
		);
	}

}
