<?php

namespace FileImporter\Services;

use FileImporter\Data\ImportPlan;
use FileImporter\Exceptions\DuplicateFilesException;
use FileImporter\Exceptions\TitleConflictException;
use FileImporter\Exceptions\TitleException;
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
	 * Validate the ImportPlan by running various checks.
	 * The order of the checks is vaguely important as some can be actively solved in the extension
	 * and other can not be.
	 * It is frustrating to the user if fix 1 thing only to then be shown another error that can not
	 * be easily fixed.
	 *
	 * @param ImportPlan $plan The plan to be validated
	 *
	 * @throws DuplicateFilesException When a file with the same hash is detected
	 * @throws TitleConflictException When either a local or remote title conflict was detected
	 */
	public function validate( ImportPlan $plan ) {
		// Checks the extension doesn't provide easy ways to fix
		$this->runDuplicateFilesCheck( $plan );
		// Checks that can be fixed in the extension
		$this->runLocalTitleConflictCheck( $plan );
		$this->runRemoteTitleConflictCheck( $plan );
		$this->runTitleCheck( $plan );
	}

	private function runTitleCheck( ImportPlan $plan ) {
		// Check to ensure files are not imported with differing file extensions.
		if (
			pathinfo( $plan->getTitle()->getText() )['extension'] !==
			pathinfo( $plan->getDetails()->getSourceLinkTarget()->getText() )['extension']
		) {
			throw new TitleException( 'Target file extension does not match original file' );
		}
	}

	private function runDuplicateFilesCheck( ImportPlan $plan ) {
		$duplicateFiles = $this->duplicateFileChecker->findDuplicates(
			$plan->getDetails()->getFileRevisions()->getLatest()
		);
		if ( !empty( $duplicateFiles ) ) {
			throw new DuplicateFilesException( $duplicateFiles );
		}
	}

	private function runLocalTitleConflictCheck( ImportPlan $plan ) {
		if ( $plan->getTitle()->exists() ) {
			throw new TitleConflictException( $plan, TitleConflictException::LOCAL_TITLE );
		}
	}

	private function runRemoteTitleConflictCheck( ImportPlan $plan ) {
		$request = $plan->getRequest();
		$details = $plan->getDetails();
		$title = $plan->getTitle();

		// Only check remotely if the title has been changed, if it is the same assume this is
		// okay / intended / other checks have happened.
		if (
			$title->getText() !== $details->getSourceLinkTarget()->getText() &&
			!$this->importTitleChecker->importAllowed( $request->getUrl(), $title->getText() )
		) {
			throw new TitleConflictException( $plan, TitleConflictException::REMOTE_TITLE );
		}
	}

}
