<?php

namespace FileImporter;

use ErrorPageError;
use Exception;
use FileImporter\Data\ImportPlan;
use FileImporter\Data\ImportRequest;
use FileImporter\Data\SourceUrl;
use FileImporter\Exceptions\DuplicateFilesException;
use FileImporter\Exceptions\ImportException;
use FileImporter\Exceptions\LocalizedImportException;
use FileImporter\Exceptions\RecoverableTitleException;
use FileImporter\Exceptions\ValidationException;
use FileImporter\Html\ChangeFileInfoForm;
use FileImporter\Html\ChangeFileNameForm;
use FileImporter\Html\DuplicateFilesPage;
use FileImporter\Html\ImportPreviewPage;
use FileImporter\Html\ImportSuccessPage;
use FileImporter\Html\InputFormPage;
use FileImporter\Html\RecoverableTitleExceptionPage;
use FileImporter\Services\Importer;
use FileImporter\Services\ImportPlanFactory;
use FileImporter\Services\SourceSiteLocator;
use Html;
use ILocalizedException;
use Liuggio\StatsdClient\Factory\StatsdDataFactoryInterface;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use Message;
use PermissionsError;
use Psr\Log\LoggerInterface;
use SpecialPage;
use UploadBase;
use User;
use UserBlockedError;

/**
 * @license GPL-2.0-or-later
 * @author Addshore
 */
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
	 * @var ImportPlanFactory
	 */
	private $importPlanFactory;

	/**
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * @var StatsdDataFactoryInterface
	 */
	private $stats;

	private $config;

	public function __construct() {
		$services = MediaWikiServices::getInstance();
		$this->config = $services->getMainConfig();

		parent::__construct(
			'FileImporter-SpecialPage',
			$this->config->get( 'FileImporterRequiredRight' ),
			!$this->config->get( 'FileImporterInBeta' )
		);

		// TODO inject services!
		$this->sourceSiteLocator = $services->getService( 'FileImporterSourceSiteLocator' );
		$this->importer = $services->getService( 'FileImporterImporter' );
		$this->importPlanFactory = $services->getService( 'FileImporterImportPlanFactory' );
		$this->logger = LoggerFactory::getInstance( 'FileImporter' );
		$this->stats = $services->getStatsdDataFactory();
	}

	/**
	 * @inheritDoc
	 */
	public function getGroupName() {
		return 'media';
	}

	/**
	 * @inheritDoc
	 */
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
			$this->stats->increment( 'FileImporter.specialPage.execute.fail.userPermissionsError' );
			throw new PermissionsError( $permissionRequired );
		}

		# Check blocks
		if ( $user->isBlocked() ) {
			$this->stats->increment( 'FileImporter.specialPage.execute.fail.userBlocked' );
			throw new UserBlockedError( $user->getBlock() );
		}

		// Global blocks
		if ( $user->isBlockedGlobally() ) {
			$this->stats->increment( 'FileImporter.specialPage.execute.fail.userGloballyBlocked' );
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

	/**
	 * @param string|null $subPage
	 */
	public function execute( $subPage ) {
		$this->executeStandardChecks();
		$this->setupPage();

		// Note: executions by users that don't have the rights to view the page etc will not be
		// shown in this metric as executeStandardChecks will have already kicked them out,
		$this->stats->increment( 'FileImporter.specialPage.execute.total' );
		// The importSource url parameter is added to requests from the FileExporter extension.
		if ( $this->getRequest()->getVal( 'importSource' ) === 'FileExporter' ) {
			$this->stats->increment( 'FileImporter.specialPage.execute.fromFileExporter' );
		}

		$clientUrl = $this->getRequest()->getVal( 'clientUrl' );

		if ( $clientUrl === null ) {
			$this->stats->increment( 'FileImporter.specialPage.execute.noClientUrl' );
			$this->showInputForm();
			return;
		}

		try {
			$this->logger->info( 'Getting ImportPlan for URL: ' . $clientUrl );
			$importPlan = $this->getImportPlan();
		} catch ( ImportException $exception ) {
			$this->logger->info( 'ImportException: ' . $exception->getMessage() );
			$this->incrementFailedImportPlanStats( $exception );
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
					( new ChangeFileNameForm( $this, $importPlan ) )->getHtml()
				);
				break;
			case 'editinfo':
				$this->getOutput()->addHTML(
					( new ChangeFileInfoForm( $this, $importPlan ) )->getHtml()
				);
				break;
			default:
				$this->showImportPage( $importPlan );
		}
	}

	private function incrementFailedImportPlanStats( Exception $exception ) {
		if ( $exception instanceof LocalizedImportException ) {
			$messageKey = $exception->getMessageObject()->getKey();
			$statKey = str_replace( 'fileimporter-', '', $messageKey );
		} else {
			$statKey = substr( strrchr( get_class( $exception ), '\\' ), 1 );
		}
		$this->stats->increment( 'FileImporter.specialPage.execute.fail.plan.total' );
		$this->stats->increment( 'FileImporter.specialPage.execute.fail.plan.byType.' . $statKey );
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
		$intendedWikiText = $this->getRequest()->getVal( 'intendedWikiText' );

		/**
		 * The below could be turned on with refactoring @ https://gerrit.wikimedia.org/r/#/c/373867/
		 * But a patch also exists to remove this code https://gerrit.wikimedia.org/r/#/c/138840/
		 */
		// if ( !$this->getRequest()->isUnicodeCompliantBrowser() ) {
		// $intendedWikiText = StringUtils::unmakeSafeForUtf8Editing( $intendedWikiText );
		// }

		$importRequest = new ImportRequest(
			$this->getRequest()->getVal( 'clientUrl' ),
			$this->getRequest()->getVal( 'intendedFileName' ),
			$intendedWikiText,
			$this->getRequest()->getVal( 'intendedRevisionSummary' )
		);

		$url = $importRequest->getUrl();
		$sourceSite = $this->sourceSiteLocator->getSourceSite( $importRequest->getUrl() );
		$url = $sourceSite->normalizeUrl( $url );

		$detailRetriever = $sourceSite->getDetailRetriever();
		$importDetails = $detailRetriever->getImportDetails( $url );

		return $this->importPlanFactory->newPlan( $importRequest, $importDetails, $this->getUser() );
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

		try {
			$result = $this->importer->import(
				$this->getUser(),
				$importPlan
			);
		} catch ( ValidationException $exception ) {
			$this->showWarningForException( $exception );
			return false;
		}

		if ( $result ) {
			$successPage = new ImportSuccessPage( $importPlan );
			$out->setPageTitle( $successPage->getPageTitle() );
			$out->addHTML( $successPage->getHtml() );
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
				Html::rawElement( 'p', [], $message )
			) .
			Html::rawElement( 'br' )
		);
	}

	private function showImportPage( ImportPlan $importPlan ) {
		$this->getOutput()->addHTML(
			( new ImportPreviewPage( $this, $importPlan ) )->getHtml()
		);
	}

	private function showInputForm( SourceUrl $sourceUrl = null ) {
		if ( $this->config->get( 'FileImporterInBeta' ) ) {
			$this->showWarningMessage( ( new Message( 'fileimporter-in-beta' ) )->parse() );
		}

		$this->getOutput()->addHTML( ( new InputFormPage( $this, $sourceUrl ) )->getHtml() );
	}

}
