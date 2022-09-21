<?php

namespace FileImporter\Services\Wikitext;

/**
 * Interface for classes processing wikitext links as found by the WikiLinkParser.
 *
 * @license GPL-2.0-or-later
 * @author Thiemo Kreuz
 */
interface WikiLinkCleaner {

	/**
	 * @param string $link The raw, untrimmed substring found between [[…]] in the wikitext.
	 *
	 * @return string Should return the original $link in case nothing was done.
	 */
	public function process( string $link ): string;

}
