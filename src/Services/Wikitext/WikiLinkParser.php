<?php

namespace FileImporter\Services\Wikitext;

/**
 * @license GPL-2.0-or-later
 * @author Thiemo Kreuz
 */
class WikiLinkParser {

	/** @var WikiLinkCleaner[] */
	private $cleaners = [];

	public function registerWikiLinkCleaner( WikiLinkCleaner $cleaner ): void {
		$this->cleaners[] = $cleaner;
	}

	public function parse( string $wikitext ): string {
		return preg_replace_callback(
			'/
				# Look-behind for the opening [[
				(?<=\[\[)
				# The extra + at the end of ++ avoids backtracking
				[^\v\[|\]]++
				# Look-ahead for | or the closing ]]
				(?=\||\]\])
			/xu',
			function ( array $matches ): string {
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
