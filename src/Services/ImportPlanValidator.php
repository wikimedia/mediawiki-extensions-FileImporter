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
	 * @param ImportPlan $importPlan The plan to be validated
	 *
	 * @throws DuplicateFilesException When a file with the same hash is detected
	 * @throws TitleConflictException When either a local or remote title conflict was detected
	 */
	public function validate( ImportPlan $importPlan ) {
		// Checks the extension doesn't provide easy ways to fix
		$this->runFileExtensionCheck( $importPlan );
		$this->runDuplicateFilesCheck( $importPlan );
		// Checks that can be fixed in the extension
		$this->runLocalTitleConflictCheck( $importPlan );
		$this->runRemoteTitleConflictCheck( $importPlan );
	}

	private function runFileExtensionCheck( ImportPlan $importPlan ) {
		$sourcePathInfo = pathinfo( $importPlan->getDetails()->getSourceLinkTarget()->getText() );
		$plannedPathInfo = pathinfo( $importPlan->getTitle()->getText() );

		// Check that both the source and planned titles have extensions
		if ( !array_key_exists( 'extension', $sourcePathInfo ) ) {
			throw new TitleException( 'Source file name does not have an extension' );
		}
		if ( !array_key_exists( 'extension', $plannedPathInfo ) ) {
			throw new TitleException( 'Planned file name does not have an extension' );
		}

		// Check to ensure files are not imported with differing file extensions.
		if ( $sourcePathInfo['extension'] !== $plannedPathInfo['extension'] ) {
			throw new TitleException( 'Target file extension does not match original file' );
		}
	}

	private function runDuplicateFilesCheck( ImportPlan $importPlan ) {
		$duplicateFiles = $this->duplicateFileChecker->findDuplicates(
			$importPlan->getDetails()->getFileRevisions()->getLatest()
		);
		if ( !empty( $duplicateFiles ) ) {
			throw new DuplicateFilesException( $duplicateFiles );
		}
	}

	private function runLocalTitleConflictCheck( ImportPlan $importPlan ) {
		if ( $importPlan->getTitle()->exists() ) {
			throw new TitleConflictException( $importPlan, TitleConflictException::LOCAL_TITLE );
		}
	}

	private function runRemoteTitleConflictCheck( ImportPlan $importPlan ) {
		$request = $importPlan->getRequest();
		$details = $importPlan->getDetails();
		$title = $importPlan->getTitle();

		// Only check remotely if the title has been changed, if it is the same assume this is
		// okay / intended / other checks have happened.
		if (
			$title->getText() !== $details->getSourceLinkTarget()->getText() &&
			!$this->importTitleChecker->importAllowed( $request->getUrl(), $title->getText() )
		) {
			throw new TitleConflictException( $importPlan, TitleConflictException::REMOTE_TITLE );
		}
	}

}
