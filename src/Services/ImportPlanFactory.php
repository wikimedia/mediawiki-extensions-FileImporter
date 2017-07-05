<?php

namespace FileImporter\Services;

use FileImporter\Data\ImportDetails;
use FileImporter\Data\ImportPlan;
use FileImporter\Data\ImportRequest;
use Flow\Import\ImportException;

class ImportPlanFactory {

	private $sourceSiteLocator;
	private $duplicateFileRevisionChecker;

	public function __construct(
		SourceSiteLocator $sourceSiteLocator,
		DuplicateFileRevisionChecker $duplicateFileRevisionChecker
	) {
		$this->sourceSiteLocator = $sourceSiteLocator;
		$this->duplicateFileRevisionChecker = $duplicateFileRevisionChecker;
	}

	/**
	 * @param ImportRequest $importRequest
	 * @param ImportDetails $importDetails
	 *
	 * @throws ImportException
	 * @return ImportPlan A valid ImportPlan
	 */
	public function newPlan( ImportRequest $importRequest, ImportDetails $importDetails ) {
		$importPlan = new ImportPlan( $importRequest, $importDetails );
		$sourceSite = $this->sourceSiteLocator->getSourceSite( $importDetails->getSourceUrl() );

		$planValidator = new ImportPlanValidator(
			$this->duplicateFileRevisionChecker,
			$sourceSite->getImportTitleChecker()
		);
		$planValidator->validate( $importPlan );

		return $importPlan;
	}

}
