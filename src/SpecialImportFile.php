<?php

namespace FileImporter;

use ErrorPageError;
use Exception;
use FileImporter\Data\ImportPlan;
use FileImporter\Data\ImportRequest;
use FileImporter\Data\SourceUrl;
use FileImporter\Exceptions\DuplicateFilesException;
use FileImporter\Exceptions\ImportException;
use FileImporter\Exceptions\RecoverableTitleException;
use FileImporter\Exceptions\TitleException;
use FileImporter\Html\ChangeTitleForm;
use FileImporter\Html\DuplicateFilesPage;
use FileImporter\Html\ImportPreviewPage;
use FileImporter\Html\ImportSuccessPage;
use FileImporter\Html\InputFormPage;
use FileImporter\Html\RecoverableTitleExceptionPage;
use FileImporter\Services\DuplicateFileRevisionChecker;
use FileImporter\Services\Importer;
use FileImporter\Services\ImportPlanValidator;
use FileImporter\Services\SourceSiteLocator;
use Html;
use ILocalizedException;
use MediaWiki\MediaWikiServices;
use Message;
use PermissionsError;
use SpecialPage;
use UploadBase;
use User;
use UserBlockedError;

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

	private function setupPage() {
		$this->getOutput()->setPageTitle( new Message( 'fileimporter-specialpage' ) );
		$this->getOutput()->enableOOUI();
		$this->getOutput()->addModuleStyles( 'ext.FileImporter.Special' );
	}

	public function execute( $subPage ) {
		$this->executeStandardChecks();
		$this->setupPage();

		if ( $this->getRequest()->getVal( 'clientUrl' ) === null ) {
			$this->showInputForm();
			return;
		}

		try {
			$importPlan = $this->getImportPlan();
		} catch ( ImportException $exception ) {
			$this->handleImportException( $exception );
			return;
		}

		switch ( $this->getRequest()->getVal( 'action' ) ) {
			case 'submit':
				if ( !$this->doImport( $importPlan ) ) {
					$this->showImportPage( $importPlan );
				}
				break;
			case 'edittitle':
				$this->getOutput()->addHTML(
					( new ChangeTitleForm( $this, $importPlan ) )->getHtml()
				);
				break;
			case 'editinfo':
				// TODO implement
			default:
				$this->showImportPage( $importPlan );
		}
	}

	/**
	 * @param ImportException $exception
	 */
	private function handleImportException( ImportException $exception ) {
		if ( $exception instanceof DuplicateFilesException ) {
				$this->getOutput()->addHTML( ( new DuplicateFilesPage(
					$exception->getFiles()
				) )->getHtml() );
				return;
		}

		if ( $exception instanceof RecoverableTitleException ) {
			$this->getOutput()->addHTML( ( new RecoverableTitleExceptionPage(
				$this,
				$exception
			) )->getHtml() );
			return;
		}

		$this->showWarningForException( $exception );
		$this->showInputForm();
	}

	/**
	 * @throws ImportException
	 * @return ImportPlan
	 */
	private function getImportPlan() {
		$importRequest = new ImportRequest(
			$this->getRequest()->getVal( 'clientUrl' ),
			$this->getRequest()->getVal( 'intendedFileName' ),
			null
		);

		$url = $importRequest->getUrl();
		$sourceSite = $this->sourceSiteLocator->getSourceSite( $importRequest->getUrl() );

		$detailRetriever = $sourceSite->getDetailRetriever();
		$importDetails = $detailRetriever->getImportDetails( $url );

		$importPlan = new ImportPlan( $importRequest, $importDetails );

		$planValidator = new ImportPlanValidator(
			$this->duplicateFileChecker,
			$sourceSite->getImportTitleChecker()
		);
		$planValidator->validate( $importPlan );

		return $importPlan;
	}

	private function doImport( ImportPlan $importPlan ) {
		$out = $this->getOutput();
		$importDetails = $importPlan->getDetails();

		$importDetailsHash = $out->getRequest()->getVal( 'importDetailsHash', '' );
		$token = $out->getRequest()->getVal( 'token', '' );

		if ( !$this->getUser()->matchEditToken( $token ) ) {
			$this->showWarningMessage( new Message( 'fileimporter-badtoken' ) );
			return false;
		}

		if ( $importDetails->getOriginalHash() !== $importDetailsHash ) {
			$this->showWarningMessage( new Message( 'fileimporter-badimporthash' ) );
			return false;
		}

		$result = $this->importer->import(
			$this->getUser(),
			$importPlan
		);

		if ( $result ) {
			$out->setPageTitle( new Message( 'fileimporter-specialpage-successtitle' ) );
			$out->addHTML( ( new ImportSuccessPage( $importPlan ) )->getHtml() );
		} else {
			$this->showWarningMessage( new Message( 'fileimporter-importfailed' ) );
		}

		return $result;
	}

	private function showWarningForException( Exception $e ) {
		if ( $e instanceof ILocalizedException ) {
			$this->showWarningMessage( $e->getMessageObject() );
		} else {
			$this->showWarningMessage( $e->getMessage() );
		}
	}

	/**
	 * @param string $message
	 */
	private function showWarningMessage( $message ) {
		$this->getOutput()->addHTML(
			Html::rawElement(
				'div',
				[ 'class' => 'warningbox' ],
				Html::element( 'p', [], $message )
			)
		);
	}

	private function showImportPage( ImportPlan $importPlan ) {
		$this->getOutput()->addHTML(
			( new ImportPreviewPage( $this, $importPlan ) )->getHtml()
		);
	}

	private function showInputForm( SourceUrl $sourceUrl = null ) {
		$this->getOutput()->addHTML( ( new InputFormPage( $this, $sourceUrl ) )->getHtml() );
	}

}
