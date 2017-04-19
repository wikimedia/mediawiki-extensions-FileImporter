<?php

namespace FileImporter;

use ErrorPageError;
use FileImporter\Data\ImportDetails;
use FileImporter\Data\SourceUrl;
use FileImporter\Exceptions\ImportException;
use FileImporter\Exceptions\SourceUrlException;
use FileImporter\Html\ChangeTitleForm;
use FileImporter\Html\DuplicateFilesPage;
use FileImporter\Html\ImportPreviewPage;
use FileImporter\Html\ImportSuccessPage;
use FileImporter\Html\InputFormPage;
use FileImporter\Html\TitleConflictPage;
use FileImporter\Interfaces\ImportTitleChecker;
use FileImporter\Services\DuplicateFileRevisionChecker;
use FileImporter\Services\Importer;
use FileImporter\Services\SourceSiteLocator;
use Html;
use MediaWiki\MediaWikiServices;
use Message;
use PermissionsError;
use SpecialPage;
use Title;
use TitleValue;
use UploadBase;
use User;
use UserBlockedError;
use WebRequest;

class SpecialImportFile extends SpecialPage {

	/**
	 * @var SourceSiteLocator
	 */
	private $sourceSiteLocator;

	/**
	 * @var Importer
	 */
	private $importer;

	/**
	 * @var DuplicateFileRevisionChecker
	 */
	private $duplicateFileChecker;

	public function __construct() {
		$services = MediaWikiServices::getInstance();
		$config = $services->getMainConfig();

		parent::__construct( 'FileImporter-SpecialPage', $config->get( 'FileImporterRequiredRight' ) );

		// TODO inject services!
		$this->sourceSiteLocator = $services->getService( 'FileImporterSourceSiteLocator' );
		$this->importer = $services->getService( 'FileImporterImporter' );
		$this->duplicateFileChecker = $services->getService( 'FileImporterDuplicateFileRevisionChecker' );
	}

	public function getGroupName() {
		return 'media';
	}

	public function userCanExecute( User $user ) {
		return UploadBase::isEnabled() && parent::userCanExecute( $user );
	}

	/**
	 * Checks based on those in SpecialUpload
	 */
	private function executeStandardChecks() {
		# Check uploading enabled
		if ( !UploadBase::isEnabled() ) {
			throw new ErrorPageError( 'uploaddisabled', 'uploaddisabledtext' );
		}

		# Check permissions
		$user = $this->getUser();
		$permissionRequired = UploadBase::isAllowed( $user );
		if ( $permissionRequired !== true ) {
			throw new PermissionsError( $permissionRequired );
		}

		# Check blocks
		if ( $user->isBlocked() ) {
			throw new UserBlockedError( $user->getBlock() );
		}

		// Global blocks
		if ( $user->isBlockedGlobally() ) {
			throw new UserBlockedError( $user->getGlobalBlock() );
		}

		# Check whether we actually want to allow changing stuff
		$this->checkReadOnly();
	}

	public function execute( $subPage ) {
		$this->executeStandardChecks();
		$out = $this->getOutput();
		$out->setPageTitle( new Message( 'fileimporter-specialpage' ) );
		$out->enableOOUI();
		$this->getOutput()->addModuleStyles( 'ext.FileImporter.Special' );

		$rawClientUrl = $out->getRequest()->getVal( 'clientUrl' );

		// If there is no input then simply show the input form.
		if ( $rawClientUrl === null ) {
			$this->showInputForm();
			return;
		}

		$sourceUrl = new SourceUrl( $rawClientUrl );

		try {
			$sourceSite = $this->sourceSiteLocator->getSourceSite( $sourceUrl );
		} catch ( SourceUrlException $e ) {
			$this->showWarningMessage( ( new Message( 'fileimporter-cantimporturl' ) )->plain() );
			$this->showInputForm();
			return;
		}

		if ( !$this->processSourceUrl( $sourceUrl ) ) {
			return;
		}

		$detailRetriever = $sourceSite->getDetailRetriever();

		try {
			$importDetails = $detailRetriever->getImportDetails( $sourceUrl );
		} catch ( ImportException $e ) {
			$this->showWarningMessage( $e->getMessage() ); // TODO i18n
			$this->showInputForm( $sourceUrl );
			return;
		}

		$intendedTitleValue = $this->getIntendedTitleValue( $importDetails, $this->getRequest() );
		$importDetails->setTargetLinkTarget( $intendedTitleValue );

		if ( !$this->processImportDetails(
			$sourceUrl,
			$importDetails,
			$sourceSite->getImportTitleChecker()
		) ) {
			return;
		}

		if ( $out->getRequest()->wasPosted() ) {
			$this->actuallyExecutePost( $importDetails );
		} else {
			$this->actuallyExecuteGet( $sourceUrl, $importDetails );
		}
	}

	/**
	 * @param SourceUrl $sourceUrl
	 *
	 * @return bool should execution continue?
	 */
	private function processSourceUrl( SourceUrl $sourceUrl ) {
		if ( !$sourceUrl->getUrl() ) {
			$this->showInputForm();
			return false;
		}

		if ( !$sourceUrl->isParsable() ) {
			$this->showWarningMessage(
				( new Message( 'fileimporter-cantparseurl' ) )->plain() . ': ' . $sourceUrl->getUrl()
			);
			$this->showInputForm();
			return false;
		}

		return true;
	}

	/**
	 * @param SourceUrl $sourceUrl
	 * @param ImportDetails $importDetails
	 * @param ImportTitleChecker $importTitleChecker
	 *
	 * @return bool should execution continue?
	 */
	private function processImportDetails(
		SourceUrl $sourceUrl,
		ImportDetails $importDetails,
		ImportTitleChecker $importTitleChecker
	) {
		$out = $this->getOutput();
		$targetTitle = $importDetails->getTargetTitle();

		if ( $targetTitle->exists() ) {
			$out->addHTML( ( new TitleConflictPage(
				$this,
				$sourceUrl,
				$targetTitle,
				'fileimporter-localtitleexists'
			) )->getHtml() );
			return false;
		}

		// Only check remotely if the title has been changed, if it is the same assume this is
		// okay / intended / other checks have happened.
		if (
			$targetTitle->getText() !== $importDetails->getOriginalLinkTarget()->getText() &&
			!$importTitleChecker->importAllowed( $sourceUrl, $targetTitle->getText() )
		) {
			$out->addHTML( ( new TitleConflictPage(
				$this,
				$sourceUrl,
				$targetTitle,
				'fileimporter-sourcetitleexists'
			) )->getHtml() );
			return false;
		}

		$duplicateFiles = $this->duplicateFileChecker->findDuplicates(
			$importDetails->getFileRevisions()->getLatest()
		);

		if ( !empty( $duplicateFiles ) ) {
			$this->getOutput()->addHTML( ( new DuplicateFilesPage( $duplicateFiles ) )->getHtml() );
			return false;
		}

		return true;
	}

	private function actuallyExecutePost( $importDetails ) {
		$importResult = $this->doImport( $importDetails );
		if ( !$importResult ) {
			$this->showImportPage( $importDetails );
		}
	}

	private function actuallyExecuteGet( $sourceUrl, ImportDetails $importDetails ) {
		$out = $this->getOutput();
		$action = $this->getRequest()->getVal( 'action' );
		if ( $action === 'edittitle' ) {
			$out->addHTML(
				( new ChangeTitleForm( $this, $sourceUrl, $importDetails->getTargetTitle() ) )
					->getHtml()
			);
		} elseif ( $action === 'editinfo' ) {
			// TODO implement form
		} else {
			$this->showImportPage( $importDetails );
		}
	}

	/**
	 * @param ImportDetails $importDetails
	 * @param WebRequest $request
	 *
	 * @return TitleValue
	 */
	private function getIntendedTitleValue( ImportDetails $importDetails, WebRequest $request ) {
		$intendedFileName = $request->getVal( 'intendedFileName' );

		if ( $intendedFileName ) {
			$intendedTitleText = $intendedFileName . '.' .
				pathinfo( $importDetails->getOriginalLinkTarget()->getText() )['extension'];
			return Title::newFromText( $intendedTitleText, NS_FILE );
		}

		$intendedTitleText = $request->getVal( 'intendedTitle' );

		if ( $intendedTitleText ) {
			return Title::newFromText( $intendedTitleText, NS_FILE );
		}

		return $importDetails->getOriginalLinkTarget();
	}

	private function doImport( ImportDetails $importDetails ) {
		$out = $this->getOutput();

		$importDetailsHash = $out->getRequest()->getVal( 'importDetailsHash', '' );
		$token = $out->getRequest()->getVal( 'token', '' );

		if ( !$this->getUser()->matchEditToken( $token ) ) {
			$this->showWarningMessage( 'Incorrect token submitted for import' ); // TODO i18n
			return false;
		}

		if ( $importDetails->getOriginalHash() !== $importDetailsHash ) {
			// TODO i18n
			$this->showWarningMessage( 'Incorrect import details hash submitted for import' );
			return false;
		}

		$result = $this->importer->import(
			$this->getUser(),
			$importDetails
		);

		if ( $result ) {
			$out->setPageTitle( new Message( 'fileimporter-specialpage-successtitle' ) );
			$this->getOutput()->addHTML( ( new ImportSuccessPage( $importDetails ) )->getHtml() );
		} else {
			$this->showWarningMessage( 'Import failed' ); // TODO i18n
		}

		return $result;
	}

	private function showWarningMessage( $message ) {
		$this->getOutput()->addHTML(
			Html::rawElement(
				'div',
				[ 'class' => 'warningbox' ],
				Html::element( 'p', [], $message )
			)
		);
	}

	private function showImportPage( ImportDetails $importDetails ) {
		$this->getOutput()->addHTML(
			( new ImportPreviewPage( $this, $importDetails ) )->getHtml()
		);
	}

	private function showInputForm( SourceUrl $sourceUrl = null ) {
		$this->getOutput()->addHTML( ( new InputFormPage( $this, $sourceUrl ) )->getHtml() );
	}

}
