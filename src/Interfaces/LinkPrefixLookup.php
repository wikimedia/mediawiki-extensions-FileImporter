<?php

namespace FileImporter\Interfaces;

use FileImporter\Data\SourceUrl;

/**
 * Interface to get prefixes for interwiki links to the source
 * if they apply.
 *
 * @license GPL-2.0-or-later
 * @author Christoph Jauera <christoph.jauera@wikimedia.de>
 */
interface LinkPrefixLookup {

	/**
	 * @param SourceUrl $sourceUrl
	 * @return string
	 */
	public function getPrefix( SourceUrl $sourceUrl ): string;

}
