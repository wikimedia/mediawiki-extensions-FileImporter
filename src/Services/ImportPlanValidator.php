<?php

namespace FileImporter\Services;

use FileImporter\Data\ImportPlan;
use FileImporter\Exceptions\DuplicateFilesException;
use FileImporter\Exceptions\RecoverableTitleException;
use FileImporter\Exceptions\TitleException;
use FileImporter\Interfaces\ImportTitleChecker;
use FileImporter\Services\UploadBase\UploadBaseFactory;
use MalformedTitleException;
use Message;
use UploadBase;

class ImportPlanValidator {

	/**
	 * @var DuplicateFileRevisionChecker
	 */
	private $duplicateFileChecker;

	/**
	 * @var ImportTitleChecker
	 */
	private $importTitleChecker;

	/**
	 * @var UploadBaseFactory
	 */
	private $uploadBaseFactory;

	public function __construct(
		DuplicateFileRevisionChecker $duplicateFileChecker,
		ImportTitleChecker $importTitleChecker,
		UploadBaseFactory $uploadBaseFactory
	) {
		$this->duplicateFileChecker = $duplicateFileChecker;
		$this->importTitleChecker = $importTitleChecker;
		$this->uploadBaseFactory = $uploadBaseFactory;
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
	 * @throws TitleException When there is a problem with the planned title (can't be fixed easily).
	 * @throws DuplicateFilesException When a file with the same hash is detected locally..
	 * @throws RecoverableTitleException When there is a problem with the title that can be fixed.
	 */
	public function validate( ImportPlan $importPlan ) {
		$this->runBasicTitleCheck( $importPlan );
		// Checks the extension doesn't provide easy ways to fix
		$this->runFileExtensionCheck( $importPlan );
		$this->runDuplicateFilesCheck( $importPlan );
		// Checks that can be fixed in the extension
		$this->runFileTitleCheck( $importPlan );
		$this->runLocalTitleConflictCheck( $importPlan );
		$this->runRemoteTitleConflictCheck( $importPlan );
	}

	private function runBasicTitleCheck( ImportPlan $importPlan ) {
		try {
			$importPlan->getTitle();
			if ( $importPlan->getRequest()->getIntendedName() !== null &&
				$importPlan->getFileName() !== $importPlan->getRequest()->getIntendedName()
			) {
				throw new RecoverableTitleException(
					new Message(
						'fileimporter-filenameerror-automaticchanges',
						[ $importPlan->getFileName() ]
					),
					$importPlan
				);
			}
		} catch ( MalformedTitleException $e ) {
			throw new RecoverableTitleException(
				$e->getMessageObject(),
				$importPlan
			);
		}
	}

	private function runFileTitleCheck( ImportPlan $importPlan ) {
		$plannedTitleText = $importPlan->getTitle()->getText();
		if ( $plannedTitleText != wfStripIllegalFilenameChars( $plannedTitleText ) ) {
			throw new RecoverableTitleException(
				'fileimporter-illegalfilenamechars',
				$importPlan
			);
		}

		$base = $this->uploadBaseFactory->newValidatingUploadBase(
			$importPlan->getTitle(),
			''
		);

		$titleCheckResult = $base->validateTitle();
		if ( $titleCheckResult !== true ) {
			switch ( $titleCheckResult ) {
				case UploadBase::ILLEGAL_FILENAME:
					$errorMessage = 'fileimporter-filenameerror-illegal';
					break;
				case UploadBase::FILENAME_TOO_LONG:
					$errorMessage = 'fileimporter-filenameerror-toolong';
					break;
				default:
					$errorMessage = 'fileimporter-filenameerror-default';
					break;
			}
			throw new RecoverableTitleException( $errorMessage, $importPlan );
		}
	}

	private function runFileExtensionCheck( ImportPlan $importPlan ) {
		$sourcePathInfo = pathinfo( $importPlan->getDetails()->getSourceLinkTarget()->getText() );
		$plannedPathInfo = pathinfo( $importPlan->getTitle()->getText() );

		// Check that both the source and planned titles have extensions
		if ( !array_key_exists( 'extension', $sourcePathInfo ) ) {
			throw new TitleException( 'fileimporter-filenameerror-nosourceextension' );
		}
		if ( !array_key_exists( 'extension', $plannedPathInfo ) ) {
			throw new TitleException( 'fileimporter-filenameerror-noplannedextension' );
		}

		// Check to ensure files are not imported with differing file extensions.
		if (
			strtolower( $sourcePathInfo['extension'] ) !==
			strtolower( $plannedPathInfo['extension'] )
		) {
			throw new TitleException( 'fileimporter-filenameerror-missmatchextension' );
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
			throw new RecoverableTitleException(
				'fileimporter-localtitleexists',
				$importPlan
			);
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
			throw new RecoverableTitleException(
				'fileimporter-sourcetitleexists',
				$importPlan
			);
		}
	}

}
