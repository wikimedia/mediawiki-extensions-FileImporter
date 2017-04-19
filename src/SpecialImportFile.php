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

		$importTitleChecker = $sourceSite->getImportTitleChecker();

		$intendedTitle = $this->getIntendedTitle( $importDetails, $this->getRequest() );
		if ( !$this->processIntendedTitle(
			$intendedTitle,
			$sourceUrl,
			$importDetails,
			$importTitleChecker
		) ) {
			return;
		}

		if ( !$this->processImportDetails( $importDetails ) ) {
			return;
		}

		if ( $out->getRequest()->wasPosted() ) {
			$this->actuallyExecutePost( $importDetails, $intendedTitle );
		} else {
			$this->actuallyExecuteGet( $sourceUrl, $importDetails, $intendedTitle );
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
	 * @param Title $intendedTitle
	 * @param SourceUrl $sourceUrl
	 * @param ImportDetails $importDetails
	 * @param ImportTitleChecker $importTitleChecker
	 *
	 * @return bool should execution continue?
	 */
	private function processIntendedTitle(
		Title $intendedTitle,
		SourceUrl $sourceUrl,
		ImportDetails $importDetails,
		ImportTitleChecker $importTitleChecker
	) {
		$out = $this->getOutput();

		if ( $intendedTitle->exists() ) {
			$out->addHTML( ( new TitleConflictPage(
				$this,
				$sourceUrl,
				$intendedTitle,
				'fileimporter-localtitleexists'
			) )->getHtml() );
			return false;
		}

		// Only check remotely if the title has been changed, if it is the same assume this is
		// okay / intended / other checks have happened.
		if (
			$intendedTitle->getPrefixedText() !== $importDetails->getPrefixedTitleText() &&
			!$importTitleChecker->importAllowed( $sourceUrl, $intendedTitle->getText() )
		) {
			$out->addHTML( ( new TitleConflictPage(
				$this,
				$sourceUrl,
				$intendedTitle,
				'fileimporter-sourcetitleexists'
			) )->getHtml() );
			return false;
		}

		return true;
	}

	/**
	 * @param ImportDetails $importDetails
	 *
	 * @return bool should execution continue?
	 */
	private function processImportDetails( ImportDetails $importDetails ) {
		$duplicateFiles = $this->duplicateFileChecker->findDuplicates(
			$importDetails->getFileRevisions()->getLatest()
		);

		if ( !empty( $duplicateFiles ) ) {
			$this->getOutput()->addHTML( ( new DuplicateFilesPage( $duplicateFiles ) )->getHtml() );
			return false;
		}

		return true;
	}

	private function actuallyExecutePost( $importDetails, $intendedTitle ) {
		$importResult = $this->doImport( $importDetails, $intendedTitle );
		if ( !$importResult ) {
			$this->showImportPage( $importDetails, $intendedTitle );
		}
	}

	private function actuallyExecuteGet( $sourceUrl, $importDetails, $intendedTitle ) {
		$out = $this->getOutput();
		$action = $this->getRequest()->getVal( 'action' );
		if ( $action === 'edittitle' ) {
			$out->addHTML(
				( new ChangeTitleForm( $this, $sourceUrl, $intendedTitle ) )->getHtml()
			);
		} elseif ( $action === 'editinfo' ) {
			// TODO implement form
		} else {
			$this->showImportPage( $importDetails, $intendedTitle );
		}
	}

	private function getIntendedTitle( ImportDetails $importDetails, WebRequest $request ) {
		$intendedFileName = $request->getVal( 'intendedFileName' );

		if ( $intendedFileName ) {
			$intendedTitleText = $intendedFileName . '.' .
				pathinfo( $importDetails->getPrefixedTitleText() )['extension'];
		} else {
			$intendedTitleText = $request->getVal(
				'intendedTitle',
				$importDetails->getPrefixedTitleText()
			);
		}

		return Title::newFromText( $intendedTitleText, NS_FILE );
	}

	private function doImport( ImportDetails $importDetails, Title $intendedTitle ) {
		$out = $this->getOutput();

		$importDetailsHash = $out->getRequest()->getVal( 'importDetailsHash', '' );
		$token = $out->getRequest()->getVal( 'token', '' );

		if ( !$this->getUser()->matchEditToken( $token ) ) {
			$this->showWarningMessage( 'Incorrect token submitted for import' ); // TODO i18n
			return false;
		}

		if ( $importDetails->getHash() !== $importDetailsHash ) {
			// TODO i18n
			$this->showWarningMessage( 'Incorrect import details hash submitted for import' );
			return false;
		}

		$result = $this->importer->import(
			$this->getUser(),
			$intendedTitle,
			$importDetails
		);

		if ( $result ) {
			$out->setPageTitle( new Message( 'fileimporter-specialpage-successtitle' ) );
			$this->getOutput()->addHTML( ( new ImportSuccessPage(
				$importDetails->getSourceUrl(),
				$intendedTitle
			) )->getHtml() );
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

	private function showImportPage( ImportDetails $importDetails, $intendedTitle ) {
		$this->getOutput()->addHTML(
			( new ImportPreviewPage( $this, $importDetails, $intendedTitle ) )
				->getHtml()
		);
	}

	private function showInputForm( SourceUrl $sourceUrl = null ) {
		$this->getOutput()->addHTML( ( new InputFormPage( $this, $sourceUrl ) )->getHtml() );
	}

}
