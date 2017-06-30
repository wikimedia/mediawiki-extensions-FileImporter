<?php

namespace FileImporter\Interfaces;

use FileImporter\Data\SourceUrl;

/**
 * This interface is used to decide if the current setup is allowed to import files form the
 * given SourceUrl.
 */
interface SourceUrlChecker {

	public function checkSourceUrl( SourceUrl $sourceUrl );

}
