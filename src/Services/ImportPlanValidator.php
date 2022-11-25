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
use MediaWiki\Permissions\Authority;
use MediaWiki\Permissions\PermissionStatus;
use MediaWiki\Permissions\RestrictionStore;
use RequestContext;
use UploadBase;

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
	private $commonsHelperConfigRetriever;

	/**
	 * @var string|null
	 */
	private $commonsHelperHelpPage;

	/**
	 * @var WikiLinkParserFactory
	 */
	private $wikiLinkParserFactory;

	/**
	 * @var RestrictionStore
	 */
	private $restrictionStore;

	/**
	 * @param DuplicateFileRevisionChecker $duplicateFileChecker
	 * @param ImportTitleChecker $importTitleChecker
	 * @param UploadBaseFactory $uploadBaseFactory
	 * @param CommonsHelperConfigRetriever|null $commonsHelperConfigRetriever
	 * @param string|null $commonsHelperHelpPage
	 * @param WikiLinkParserFactory $wikiLinkParserFactory
	 * @param RestrictionStore $restrictionStore
	 */
	public function __construct(
		DuplicateFileRevisionChecker $duplicateFileChecker,
		ImportTitleChecker $importTitleChecker,
		UploadBaseFactory $uploadBaseFactory,
		?CommonsHelperConfigRetriever $commonsHelperConfigRetriever,
		$commonsHelperHelpPage,
		WikiLinkParserFactory $wikiLinkParserFactory,
		RestrictionStore $restrictionStore
	) {
		$this->duplicateFileChecker = $duplicateFileChecker;
		$this->importTitleChecker = $importTitleChecker;
		$this->uploadBaseFactory = $uploadBaseFactory;
		$this->commonsHelperConfigRetriever = $commonsHelperConfigRetriever;
		$this->commonsHelperHelpPage = $commonsHelperHelpPage;
		$this->wikiLinkParserFactory = $wikiLinkParserFactory;
		$this->restrictionStore = $restrictionStore;
	}

	/**
	 * Validate the ImportPlan by running various checks.
	 * The order of the checks is vaguely important as some can be actively solved in the extension
	 * and others cannot.
	 * It's frustrating to the user if they fix one thing, only to be shown another error that
	 * cannot be easily fixed.
	 *
	 * @param ImportPlan $importPlan The plan to be validated
	 * @param Authority $user User that executes the import
	 *
	 * @throws TitleException When there is a problem with the planned title (can't be fixed easily).
	 * @throws DuplicateFilesException When a file with the same hash is detected locally..
	 * @throws RecoverableTitleException When there is a problem with the title that can be fixed.
	 */
	public function validate( ImportPlan $importPlan, Authority $user ) {
		// Have to run this first because other tests don't make sense without basic title sanity.
		$this->runBasicTitleCheck( $importPlan );

		// Unrecoverable errors
		$this->runPermissionTitleChecks( $importPlan, $user );
		$this->runFileExtensionCheck( $importPlan );
		$this->runDuplicateFilesCheck( $importPlan );

		// Conversions
		$this->runCommonsHelperChecksAndConversions( $importPlan );
		$this->runWikiLinkConversions( $importPlan );

		// Solvable errors
		$this->warnOnAutomaticTitleChanges( $importPlan );
		$this->runFileTitleCheck( $importPlan );
		$this->runLocalTitleConflictCheck( $importPlan );
		$this->runRemoteTitleConflictCheck( $importPlan );
	}

	/**
	 * @param ImportPlan $importPlan
	 */
	private function runCommonsHelperChecksAndConversions( ImportPlan $importPlan ): void {
		if ( !$this->commonsHelperConfigRetriever ) {
			return;
		}

		$details = $importPlan->getDetails();
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
		$this->cleanWikitext( $importPlan, $commonHelperConfigParser->getWikitextConversions() );
	}

	/**
	 * @param ImportDetails $details
	 * @param WikitextConversions $conversions
	 */
	private function runLicenseChecks( ImportDetails $details, WikitextConversions $conversions ): void {
		$validator = new FileDescriptionPageValidator( $conversions );
		$validator->hasRequiredTemplate( $details->getTemplates() );
		$validator->validateTemplates( $details->getTemplates() );
		$validator->validateCategories( $details->getCategories() );
	}

	/**
	 * @param ImportPlan $importPlan
	 * @param WikitextConversions $conversions
	 */
	private function cleanWikitext( ImportPlan $importPlan, WikitextConversions $conversions ): void {
		$wikitext = $importPlan->getCleanedLatestRevisionText();
		$cleaner = new WikitextContentCleaner( $conversions );

		$sourceLanguage = $importPlan->getDetails()->getPageLanguage();
		if ( $sourceLanguage ) {
			$languageTemplate = \Title::makeTitleSafe( NS_TEMPLATE, $sourceLanguage );
			if ( $languageTemplate->exists() ) {
				$cleaner->setSourceWikiLanguageTemplate( $sourceLanguage );
			}
		}

		$importPlan->setCleanedLatestRevisionText( $cleaner->cleanWikitext( $wikitext ) );
		$importPlan->setNumberOfTemplateReplacements( $cleaner->getLatestNumberOfReplacements() );
	}

	/**
	 * @param ImportPlan $importPlan
	 */
	private function runWikiLinkConversions( ImportPlan $importPlan ): void {
		$parser = $this->wikiLinkParserFactory->getWikiLinkParser(
			$importPlan->getDetails()->getPageLanguage(),
			$importPlan->getInterWikiPrefix()
		);
		$wikitext = $importPlan->getCleanedLatestRevisionText();
		$importPlan->setCleanedLatestRevisionText( $parser->parse( $wikitext ) );
	}

	/**
	 * @param ImportPlan $importPlan
	 */
	private function runBasicTitleCheck( ImportPlan $importPlan ): void {
		try {
			$importPlan->getTitle();
		} catch ( MalformedTitleException $e ) {
			throw new RecoverableTitleException(
				$e->getMessageObject(),
				$importPlan,
				$e
			);
		}
	}

	/**
	 * @param ImportPlan $importPlan
	 */
	private function warnOnAutomaticTitleChanges( ImportPlan $importPlan ): void {
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
	}

	/**
	 * @param ImportPlan $importPlan
	 * @param Authority $user
	 */
	private function runPermissionTitleChecks( ImportPlan $importPlan, Authority $user ): void {
		$title = $importPlan->getTitle();

		/**
		 * {@see UploadBase::verifyTitlePermissions}
		 */
		$status = PermissionStatus::newEmpty();
		$user->authorizeWrite( 'edit', $title, $status );
		$user->authorizeWrite( 'upload', $title, $status );
		if ( !$status->isGood() ) {
			$permErrors = $status->toLegacyErrorArray();
			throw new RecoverableTitleException( $permErrors[0], $importPlan );
		}

		// Even administrators should not (accidentally) move a file to a protected file name
		if ( $this->restrictionStore->isProtected( $title ) ) {
			throw new RecoverableTitleException( 'fileimporter-filenameerror-protected', $importPlan );
		}
	}

	/**
	 * @return string
	 */
	private function getAllowedFileExtensions() {
		$config = MediaWikiServices::getInstance()->getMainConfig();
		$fileExtensions = array_unique( $config->get( 'FileExtensions' ) );
		$language = RequestContext::getMain()->getLanguage();
		return $language->listToText( $fileExtensions );
	}

	/**
	 * @param ImportPlan $importPlan
	 */
	private function runFileTitleCheck( ImportPlan $importPlan ): void {
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
		switch ( $base->validateTitle() ) {
			case UploadBase::OK:
				return;

			case UploadBase::FILETYPE_BADTYPE:
				// Stop the import early if the extension is not allowed on the destination wiki
				throw new TitleException( [
					'fileimporter-filenameerror-notallowed',
					$importPlan->getFileExtension(),
					$this->getAllowedFileExtensions()
				] );

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

	/**
	 * @param ImportPlan $importPlan
	 */
	private function runFileExtensionCheck( ImportPlan $importPlan ): void {
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

	/**
	 * @param ImportPlan $importPlan
	 */
	private function runDuplicateFilesCheck( ImportPlan $importPlan ): void {
		$duplicateFiles = $this->duplicateFileChecker->findDuplicates(
			$importPlan->getDetails()->getFileRevisions()->getLatest()
		);

		if ( $duplicateFiles !== [] ) {
			throw new DuplicateFilesException( $duplicateFiles );
		}
	}

	/**
	 * @param ImportPlan $importPlan
	 */
	private function runLocalTitleConflictCheck( ImportPlan $importPlan ): void {
		if ( $importPlan->getTitle()->exists() ) {
			throw new RecoverableTitleException(
				'fileimporter-localtitleexists',
				$importPlan
			);
		}
	}

	/**
	 * @param ImportPlan $importPlan
	 */
	private function runRemoteTitleConflictCheck( ImportPlan $importPlan ): void {
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
