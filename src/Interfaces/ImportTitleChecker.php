<?php

namespace FileImporter\Interfaces;

use FileImporter\Data\SourceUrl;

interface ImportTitleChecker {

	/**
	 * @param SourceUrl $sourceUrl
	 * @param string $titleString Foo.jpg or Berlin.png
	 *
	 * @return bool is the import allowed
	 */
	public function importAllowed( SourceUrl $sourceUrl, $titleString );

}
