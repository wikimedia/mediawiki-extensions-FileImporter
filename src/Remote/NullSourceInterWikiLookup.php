<?php

namespace FileImporter\Remote;

use FileImporter\Data\SourceUrl;
use FileImporter\Interfaces\SourceInterWikiLookup;

/**
 * Plain SourceInterWikiLookup implementation returning an empty string
 * as prefix.
 *
 * @license GPL-2.0-or-later
 * @author Christoph Jauera <christoph.jauera@wikimedia.de>
 *
 * @codeCoverageIgnore
 */
class NullSourceInterWikiLookup implements SourceInterWikiLookup {

	/**
	 * @inheritDoc
	 */
	public function getPrefix( SourceUrl $sourceUrl ) {
		return '';
	}

}
