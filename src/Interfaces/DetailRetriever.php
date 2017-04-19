<?php

namespace FileImporter\Interfaces;

use FileImporter\Data\ImportDetails;
use FileImporter\Data\SourceUrl;
use FileImporter\Exceptions\SourceUrlException;

/**
 * This interface creates ImportDetails objects from a SourceUrl.
 */
interface DetailRetriever {

	/**
	 * @param SourceUrl $sourceUrl
	 *
	 * @return ImportDetails
	 * @throws SourceUrlException if the given target can't be imported by this importer
	 */
	public function getImportDetails( SourceUrl $sourceUrl );

}
