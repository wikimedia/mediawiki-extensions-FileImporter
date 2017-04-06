<?php

namespace FileImporter;

use ErrorPageError;
use FileImporter\Generic\Data\ImportTransformations;
use FileImporter\Generic\Data\ImportDetails;
use FileImporter\Generic\Exceptions\ImportException;
use FileImporter\Generic\Services\DetailRetriever;
use FileImporter\Generic\Services\DuplicateFileRevisionChecker;
use FileImporter\Generic\Services\Importer;
use FileImporter\Generic\Data\TargetUrl;
use FileImporter\Html\DuplicateFilesPage;
use FileImporter\Html\ImportPreviewPage;
use FileImporter\Html\ImportSuccessPage;
use FileImporter\Html\InputFormPage;
use Html;
use MediaWiki\MediaWikiServices;
use Message;
use PermissionsError;
use SpecialPage;
use Title;
use UploadBase;
use User;
use UserBlockedError;

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
		parent::__construct( 'FileImporter-SpecialPage' );
		$services = MediaWikiServices::getInstance();
		$config = $services->getMainConfig();

		parent::__construct( 'ImportFile', $config->get( 'FileImporterRequiredRight' ) );

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

		if ( !$targetUrl->getUrl() ) {
			$this->showInputForm();
			return;
		}

		if ( !$targetUrl->isParsable() ) {
			$this->showWarningMessage(
				( new Message( 'fileimporter-cantparseurl' ) )->plain() . ': ' . $targetUrl->getUrl()
			);
			$this->showInputForm();
			return;
		}

		if ( !$this->detailRetreiver->canGetImportDetails( $targetUrl ) ) {
			$this->showWarningMessage( ( new Message( 'fileimporter-cantimporturl' ) )->plain() );
			$this->showInputForm();
			return;
		}

		try {
			$importDetails = $this->detailRetreiver->getImportDetails( $targetUrl );
		} catch ( ImportException $e ) {
			$this->showWarningMessage( $e->getMessage() ); // TODO i18n
			$this->showInputForm( $targetUrl );
			return;
		}

		$duplicateFiles = $this->duplicateFileChecker->findDuplicates(
			$importDetails->getFileRevisions()->getLatest()
		);
		if ( !empty( $duplicateFiles ) ) {
			$out->addHTML( ( new DuplicateFilesPage( $duplicateFiles ) )->getHtml() );
			return;
		}

		if ( $wasPosted ) {
			$importResult = $this->doImport( $importDetails );
			if ( !$importResult ) {
				$this->showImportPage( $importDetails );
			}
		} else {
			$this->showImportPage( $importDetails );
		}
	}

	private function doImport( ImportDetails $importDetails ) {
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

		$adjustments = new ImportTransformations(); // TODO populate adjustments based on import form

		$result = $this->importer->import(
			$this->getUser(),
			$importDetails,
			$adjustments
		);

		if ( $result ) {
			$out->setPageTitle( new Message( 'fileimporter-specialpage-successtitle' ) );
			$this->getOutput()->addHTML( ( new ImportSuccessPage(
				$importDetails->getTargetUrl(),
				Title::newFromText( $importDetails->getTitleText(), NS_FILE )
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

	private function showImportPage( ImportDetails $importDetails ) {
		$this->getOutput()->addHTML( ( new ImportPreviewPage( $this, $importDetails ) )->getHtml() );
	}

	private function showInputForm( TargetUrl $targetUrl = null ) {
		$this->getOutput()->addHTML( ( new InputFormPage( $this, $targetUrl ) )->getHtml() );
	}

}
