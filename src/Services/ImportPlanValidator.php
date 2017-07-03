<?php

namespace FileImporter\Services;

use FileImporter\Data\ImportPlan;
use FileImporter\Exceptions\DuplicateFilesException;
use FileImporter\Exceptions\TitleConflictException;
use FileImporter\Interfaces\ImportTitleChecker;

class ImportPlanValidator {

	/**
	 * @var DuplicateFileRevisionChecker
	 */
	private $duplicateFileChecker;

	/**
	 * @var ImportTitleChecker
	 */
	private $importTitleChecker;

	public function __construct(
		DuplicateFileRevisionChecker $duplicateFileChecker,
		ImportTitleChecker $importTitleChecker
	) {
		$this->duplicateFileChecker = $duplicateFileChecker;
		$this->importTitleChecker = $importTitleChecker;
	}

	/**
	 * @param ImportPlan $plan The plan to be validated
	 *
	 * @throws DuplicateFilesException When a file with the same hash is detected
	 * @throws TitleConflictException When either a local or remote title conflict was detected
	 */
	public function validate( ImportPlan $plan ) {
		$request = $plan->getRequest();
		$details = $plan->getDetails();

		$duplicateFiles = $this->duplicateFileChecker->findDuplicates(
			$details->getFileRevisions()->getLatest()
		);
		if ( !empty( $duplicateFiles ) ) {
			throw new DuplicateFilesException( $duplicateFiles );
		}

		$targetTitle = $plan->getTitle();
		if ( $targetTitle->exists() ) {
			throw new TitleConflictException( $plan, TitleConflictException::LOCAL_TITLE );
		}

		// Only check remotely if the title has been changed, if it is the same assume this is
		// okay / intended / other checks have happened.
		if (
			$targetTitle->getText() !== $details->getSourceLinkTarget()->getText() &&
			!$this->importTitleChecker->importAllowed( $request->getUrl(), $targetTitle->getText() )
		) {
			throw new TitleConflictException( $plan, TitleConflictException::REMOTE_TITLE );
		}
	}

}
