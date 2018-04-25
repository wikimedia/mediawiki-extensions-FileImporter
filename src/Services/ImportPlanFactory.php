<?php

namespace FileImporter\Services;

use FileImporter\Data\ImportDetails;
use FileImporter\Data\ImportPlan;
use FileImporter\Data\ImportRequest;
use FileImporter\Exceptions\ImportException;
use FileImporter\Services\UploadBase\UploadBaseFactory;
use User;

/**
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class ImportPlanFactory {

	private $sourceSiteLocator;
	private $duplicateFileRevisionChecker;
	private $uploadBaseFactory;

	public function __construct(
		SourceSiteLocator $sourceSiteLocator,
		DuplicateFileRevisionChecker $duplicateFileRevisionChecker,
		UploadBaseFactory $uploadBaseFactory
	) {
		$this->sourceSiteLocator = $sourceSiteLocator;
		$this->duplicateFileRevisionChecker = $duplicateFileRevisionChecker;
		$this->uploadBaseFactory = $uploadBaseFactory;
	}

	/**
	 * @param ImportRequest $importRequest
	 * @param ImportDetails $importDetails
	 * @param User $user
	 *
	 * @throws ImportException
	 * @return ImportPlan A valid ImportPlan
	 */
	public function newPlan( ImportRequest $importRequest, ImportDetails $importDetails, User $user ) {
		$importPlan = new ImportPlan( $importRequest, $importDetails );
		$sourceSite = $this->sourceSiteLocator->getSourceSite( $importDetails->getSourceUrl() );

		$planValidator = new ImportPlanValidator(
			$this->duplicateFileRevisionChecker,
			$sourceSite->getImportTitleChecker(),
			$this->uploadBaseFactory
		);
		$planValidator->validate( $importPlan, $user );

		return $importPlan;
	}

}
