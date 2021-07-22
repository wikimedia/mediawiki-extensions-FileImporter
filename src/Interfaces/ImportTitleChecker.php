<?php

namespace FileImporter\Interfaces;

use FileImporter\Data\SourceUrl;

/**
 * Interface used to decide if the intended title of a file is allowed based on the SourceUrl.
 * This usually means making requests to the site hosting the SourceUrl to get data.
 *
 * @license GPL-2.0-or-later
 * @author Addshore
 */
interface ImportTitleChecker {

	/**
	 * @param SourceUrl $sourceUrl
	 * @param string $intendedFileName File name without the File: prefix
	 *
	 * @return bool is the import allowed
	 */
	public function importAllowed( SourceUrl $sourceUrl, string $intendedFileName ): bool;

}
