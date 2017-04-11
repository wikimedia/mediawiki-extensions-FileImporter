<?php

namespace FileImporter;

use ErrorPageError;
use FileImporter\Generic\Data\ImportDetails;
use FileImporter\Generic\Exceptions\ImportException;
use FileImporter\Generic\Services\DetailRetriever;
use FileImporter\Generic\Services\DuplicateFileRevisionChecker;
use FileImporter\Generic\Services\Importer;
use FileImporter\Generic\Data\TargetUrl;
use FileImporter\Html\ChangeTitleForm;
use FileImporter\Html\DuplicateFilesPage;
use FileImporter\Html\ImportPreviewPage;
use FileImporter\Html\ImportSuccessPage;
use FileImporter\Html\InputFormPage;
use FileImporter\Html\LocalTitleExistsPage;
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
	 * @var DetailRetriever
	 */
	private $detailRetreiver;

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
		$this->detailRetreiver = $services->getService( 'FileImporterDispatchingDetailRetriever' );
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

		$targetUrl = new TargetUrl( $out->getRequest()->getVal( 'clientUrl', '' ) );
		$wasPosted = $out->getRequest()->wasPosted();

		$this->getOutput()->addModuleStyles( 'ext.FileImporter.Special' );

		if ( !$this->processTargetUrl( $targetUrl ) ) {
			return;
		}

		try {
			$importDetails = $this->detailRetreiver->getImportDetails( $targetUrl );
		} catch ( ImportException $e ) {
			$this->showWarningMessage( $e->getMessage() ); // TODO i18n
			$this->showInputForm( $targetUrl );
			return;
		}

		$intendedTitle = $this->getIntendedTitle( $importDetails, $this->getRequest() );
		if ( !$this->processIntendedTitle( $intendedTitle, $targetUrl, $importDetails ) ) {
			return;
		}

		if ( !$this->processImportDetails( $importDetails ) ) {
			return;
		}

		if ( $wasPosted ) {
			$this->actuallyExecutePost( $importDetails, $intendedTitle );
		} else {
			$this->actuallyExecuteGet( $targetUrl, $importDetails, $intendedTitle );
		}
	}

	/**
	 * @param TargetUrl $targetUrl
	 *
	 * @return bool should execution continue?
	 */
	private function processTargetUrl( TargetUrl $targetUrl ) {
		if ( !$targetUrl->getUrl() ) {
			$this->showInputForm();
			return false;
		}

		if ( !$targetUrl->isParsable() ) {
			$this->showWarningMessage(
				( new Message( 'fileimporter-cantparseurl' ) )->plain() . ': ' . $targetUrl->getUrl()
			);
			$this->showInputForm();
			return false;
		}

		if ( !$this->detailRetreiver->canGetImportDetails( $targetUrl ) ) {
			$this->showWarningMessage( ( new Message( 'fileimporter-cantimporturl' ) )->plain() );
			$this->showInputForm();
			return false;
		}

		return true;
	}

	/**
	 * @param Title $intendedTitle
	 * @param TargetUrl $targetUrl
	 * @param ImportDetails $importDetails
	 *
	 * @return bool should execution continue?
	 */
	private function processIntendedTitle(
		Title $intendedTitle,
		TargetUrl $targetUrl,
		ImportDetails $importDetails
	) {
		$out = $this->getOutput();

		if ( $intendedTitle->exists() ) {
			$out->addHTML( ( new LocalTitleExistsPage( $this, $targetUrl, $intendedTitle ) )->getHtml() );
			return false;
		}

		if ( $intendedTitle->getPrefixedText() !== $importDetails->getPrefixedTitleText() ) {
			// TODO check back on source site to see if the new title exists there?
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

	private function actuallyExecuteGet( $targetUrl, $importDetails, $intendedTitle ) {
		$out = $this->getOutput();
		$action = $this->getRequest()->getVal( 'action' );
		if ( $action === 'edittitle' ) {
			$out->addHTML(
				( new ChangeTitleForm( $this, $targetUrl, $intendedTitle ) )->getHtml()
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
				$importDetails->getTargetUrl(),
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

	private function showInputForm( TargetUrl $targetUrl = null ) {
		$this->getOutput()->addHTML( ( new InputFormPage( $this, $targetUrl ) )->getHtml() );
	}

}
