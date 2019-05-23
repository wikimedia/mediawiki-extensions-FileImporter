<?php

namespace FileImporter\Services;

use FileImporter\Data\ImportDetails;
use FileImporter\Data\ImportPlan;
use FileImporter\Data\WikitextConversions;
use FileImporter\Exceptions\DuplicateFilesException;
use FileImporter\Exceptions\LocalizedImportException;
use FileImporter\Exceptions\RecoverableTitleException;
use FileImporter\Exceptions\TitleException;
use FileImporter\Interfaces\ImportTitleChecker;
use FileImporter\Remote\MediaWiki\CommonsHelperConfigRetriever;
use FileImporter\Services\UploadBase\UploadBaseFactory;
use FileImporter\Services\Wikitext\CommonsHelperConfigParser;
use FileImporter\Services\Wikitext\WikiLinkParserFactory;
use FileImporter\Services\Wikitext\WikitextContentCleaner;
use MalformedTitleException;
use MediaWiki\MediaWikiServices;
use UploadBase;
use User;

/**
 * @license GPL-2.0-or-later
 * @author Addshore
 */
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

	/**
	 * @var CommonsHelperConfigRetriever|null
	 */
	private $commonsHelperConfigRetriever = null;

	/**
	 * @var string|null
	 */
	private $commonsHelperHelpPage = null;

	/**
	 * @var WikiLinkParserFactory
	 */
	private $wikiLinkParserFactory;

	public function __construct(
		DuplicateFileRevisionChecker $duplicateFileChecker,
		ImportTitleChecker $importTitleChecker,
		UploadBaseFactory $uploadBaseFactory
	) {
		$this->duplicateFileChecker = $duplicateFileChecker;
		$this->importTitleChecker = $importTitleChecker;
		$this->uploadBaseFactory = $uploadBaseFactory;

		// FIXME: Inject!
		$services = MediaWikiServices::getInstance();
		$config = $services->getMainConfig();
		$commonsHelperServer = $config->get( 'FileImporterCommonsHelperServer' );
		$httpRequestExecutor = $services->getService( 'FileImporterHttpRequestExecutor' );

		if ( $commonsHelperServer ) {
			$this->commonsHelperConfigRetriever = new CommonsHelperConfigRetriever(
				$httpRequestExecutor,
				$commonsHelperServer,
				$config->get( 'FileImporterCommonsHelperBasePageName' )
			);
			$this->commonsHelperHelpPage = $config->get( 'FileImporterCommonsHelperHelpPage' ) ?:
				$commonsHelperServer;
		}

		$this->wikiLinkParserFactory = new WikiLinkParserFactory();
	}

	/**
	 * Validate the ImportPlan by running various checks.
	 * The order of the checks is vaguely important as some can be actively solved in the extension
	 * and other can not be.
	 * It is frustrating to the user if fix 1 thing only to then be shown another error that can not
	 * be easily fixed.
	 *
	 * @param ImportPlan $importPlan The plan to be validated
	 * @param User $user User that executes the import
	 *
	 * @throws TitleException When there is a problem with the planned title (can't be fixed easily).
	 * @throws DuplicateFilesException When a file with the same hash is detected locally..
	 * @throws RecoverableTitleException When there is a problem with the title that can be fixed.
	 */
	public function validate( ImportPlan $importPlan, User $user ) {
		// FIXME: The fact this does not even need an ImportPlan but only the ImportDetails is a
		// little weird and possibly a sign this code is still misplaced here. Is this a problem?
		$this->runCommonsHelperChecksAndConversions( $importPlan->getDetails() );
		$this->runWikiLinkConversions( $importPlan );

		$this->runBasicTitleCheck( $importPlan );
		$this->runPermissionTitleChecks( $importPlan, $user );
		// Checks the extension doesn't provide easy ways to fix
		$this->runFileExtensionCheck( $importPlan );
		$this->runDuplicateFilesCheck( $importPlan );
		// Checks that can be fixed in the extension
		$this->runFileTitleCheck( $importPlan );
		$this->runLocalTitleConflictCheck( $importPlan );
		$this->runRemoteTitleConflictCheck( $importPlan );
	}

	private function runCommonsHelperChecksAndConversions( ImportDetails $details ) {
		if ( !$this->commonsHelperConfigRetriever ) {
			return;
		}

		$sourceUrl = $details->getSourceUrl();

		if ( !$this->commonsHelperConfigRetriever->retrieveConfiguration( $sourceUrl ) ) {
			throw new LocalizedImportException( [
				'fileimporter-commonshelper-missing-config',
				$sourceUrl->getHost(),
				$this->commonsHelperHelpPage
			] );
		}

		$commonHelperConfigParser = new CommonsHelperConfigParser(
			$this->commonsHelperConfigRetriever->getConfigWikiUrl(),
			$this->commonsHelperConfigRetriever->getConfigWikitext()
		);

		$this->runLicenseChecks( $details, $commonHelperConfigParser->getWikitextConversions() );
		$this->cleanWikitext( $details, $commonHelperConfigParser->getWikitextConversions() );
	}

	private function runLicenseChecks( ImportDetails $details, WikitextConversions $conversions ) {
		$validator = new FileDescriptionPageValidator( $conversions );
		$validator->hasRequiredTemplate( $details->getTemplates() );
		$validator->validateTemplates( $details->getTemplates() );
		$validator->validateCategories( $details->getCategories() );
	}

	private function cleanWikitext( ImportDetails $details, WikitextConversions $conversions ) {
		$wikitext = $details->getCleanedRevisionText();
		$cleaner = new WikitextContentCleaner( $conversions );
		$details->setCleanedRevisionText( $cleaner->cleanWikitext( $wikitext ) );
		$details->setNumberOfTemplatesReplaced( $cleaner->getLatestNumberOfReplacements() );
	}

	private function runWikiLinkConversions( ImportPlan $importPlan ) {
		$details = $importPlan->getDetails();
		$sourceLanguage = $details->getPageLanguage();
		$wikitext = $details->getCleanedRevisionText();
		$details->setCleanedRevisionText( $this->wikiLinkParserFactory->getWikiLinkParser(
			$sourceLanguage ? \Language::factory( $sourceLanguage ) : null,
			$importPlan->getInterWikiPrefix()
		)->parse( $wikitext ) );
	}

	private function runBasicTitleCheck( ImportPlan $importPlan ) {
		try {
			$importPlan->getTitle();
			if ( $importPlan->getRequest()->getIntendedName() !== null &&
				$importPlan->getFileName() !== $importPlan->getRequest()->getIntendedName()
			) {
				throw new RecoverableTitleException(
					[
						'fileimporter-filenameerror-automaticchanges',
						$importPlan->getRequest()->getIntendedName(),
						$importPlan->getFileName()
					],
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

	private function runPermissionTitleChecks( ImportPlan $importPlan, User $user ) {
		$permErrors = $importPlan->getTitle()->getUserPermissionsErrors( 'upload', $user );

		if ( $permErrors !== [] ) {
			throw new RecoverableTitleException( $permErrors[0], $importPlan );
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

		if ( $duplicateFiles !== [] ) {
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
