<?php

namespace FileImporter\Generic;

use FileImporter\Generic\Exceptions\ImportTargetException;

interface Importer {

	/**
	 * @param TargetUrl $targetUrl
	 *
	 * @return bool
	 */
	public function canImport( TargetUrl $targetUrl );

	/**
	 * @param TargetUrl $targetUrl
	 *
	 * @return ImportDetails
	 * @throws ImportTargetException if the given target can't be imported by this importer
	 */
	public function getImportDetails( TargetUrl $targetUrl );

	/**
	 * @param TargetUrl $targetUrl
	 * @param ImportAdjustments $importAdjustments
	 *
	 * @return bool success
	 * @throws ImportTargetException if the given target can't be imported by this importer
	 */
	public function import( TargetUrl $targetUrl, ImportAdjustments $importAdjustments );

}
