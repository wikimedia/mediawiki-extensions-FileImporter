<?php

namespace FileImporter\Generic\Services;

use FileImporter\Generic\Data\ImportDetails;
use FileImporter\Generic\Data\TargetUrl;
use FileImporter\Generic\Exceptions\ImportTargetException;

/**
 * This interface creates ImportDetails objects from a TargetUrl.
 */
interface DetailRetriever {

	/**
	 * @param TargetUrl $targetUrl
	 *
	 * @return bool
	 */
	public function canGetImportDetails( TargetUrl $targetUrl );

	/**
	 * @param TargetUrl $targetUrl
	 *
	 * @return ImportDetails
	 * @throws ImportTargetException if the given target can't be imported by this importer
	 */
	public function getImportDetails( TargetUrl $targetUrl );

}
