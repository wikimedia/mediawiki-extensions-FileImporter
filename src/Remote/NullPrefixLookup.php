<?php

namespace FileImporter\Remote;

use FileImporter\Data\SourceUrl;
use FileImporter\Interfaces\LinkPrefixLookup;

/**
 * Plain LinkPrefixLookup implementation returning an empty string
 * as prefix.
 *
 * @license GPL-2.0-or-later
 * @author Christoph Jauera <christoph.jauera@wikimedia.de>
 *
 * @codeCoverageIgnore
 */
class NullPrefixLookup implements LinkPrefixLookup {

	/**
	 * @inheritDoc
	 */
	public function getPrefix( SourceUrl $sourceUrl ): string {
		return '';
	}

}
