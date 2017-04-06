<?php

namespace FileImporter\Interfaces;

use FileImporter\Data\SourceUrl;

interface SourceUrlChecker {

	public function checkSourceUrl( SourceUrl $sourceUrl );

}
