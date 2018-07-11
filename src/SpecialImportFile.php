<?php

namespace FileImporter;

use ErrorPageError;
use Exception;
use FileImporter\Data\ImportPlan;
use FileImporter\Data\ImportRequest;
use FileImporter\Exceptions\DuplicateFilesException;
use FileImporter\Exceptions\ImportException;
use FileImporter\Exceptions\LocalizedImportException;
use FileImporter\Exceptions\RecoverableTitleException;
use FileImporter\Exceptions\ValidationException;
use FileImporter\Html\ErrorPage;
use FileImporter\Html\ChangeFileInfoForm;
use FileImporter\Html\ChangeFileNameForm;
use FileImporter\Html\DuplicateFilesErrorPage;
use FileImporter\Html\FileInfoDiffPage;
use FileImporter\Html\ImportPreviewPage;
use FileImporter\Html\ImportSuccessPage;
use FileImporter\Html\InputFormPage;
use FileImporter\Html\RecoverableTitleExceptionPage;
use FileImporter\Html\InfoPage;
use FileImporter\Services\Importer;
use FileImporter\Services\ImportPlanFactory;
use FileImporter\Services\SourceSiteLocator;
use Html;
use ILocalizedException;
use Liuggio\StatsdClient\Factory\StatsdDataFactoryInterface;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
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

	const ACTION_EDIT_TITLE = 'edittitle';
	const ACTION_EDIT_INFO = 'editinfo';
	const ACTION_SUBMIT = 'submit';
	const ACTION_VIEW_DIFF = 'viewdiff';

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
			$this->config->get( 'FileImporterShowInputScreen' )
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
		$this->getOutput()->setPageTitle( wfMessage( 'fileimporter-specialpage' ) );
		$this->getOutput()->enableOOUI();
		$this->getOutput()->addModuleStyles( 'ext.FileImporter.Special' );
	}

	/**
	 * @param string|null $subPage
	 */
	public function execute( $subPage ) {
		$this->setupPage();
		$clientUrl = $this->getRequest()->getVal( 'clientUrl' );
		$this->executeStandardChecks();

		// Note: executions by users that don't have the rights to view the page etc will not be
		// shown in this metric as executeStandardChecks will have already kicked them out,
		$this->stats->increment( 'FileImporter.specialPage.execute.total' );
		// The importSource url parameter is added to requests from the FileExporter extension.
		if ( $this->getRequest()->getVal( 'importSource' ) === 'FileExporter' ) {
			$this->stats->increment( 'FileImporter.specialPage.execute.fromFileExporter' );
		}

		if ( $clientUrl === null ) {
			$this->stats->increment( 'FileImporter.specialPage.execute.noClientUrl' );
			$this->showLandingPage();
			return;
		}

		try {
			$this->logger->info( 'Getting ImportPlan for URL: ' . $clientUrl );
			$importPlan = $this->getImportPlan();
		} catch ( ImportException $exception ) {
			$this->logger->info( 'ImportException: ' . $exception->getMessage() );
			$this->incrementFailedImportPlanStats( $exception );
			$this->handleImportException( $exception, $clientUrl );
			return;
		}

		switch ( $this->getRequest()->getVal( 'action' ) ) {
			case self::ACTION_SUBMIT:
				if ( !$this->doImport( $importPlan ) ) {
					$this->showImportPage( $importPlan );
				}
				break;
			case self::ACTION_EDIT_TITLE:
				$this->getOutput()->addHTML(
					( new ChangeFileNameForm( $this, $importPlan ) )->getHtml()
				);
				break;
			case self::ACTION_EDIT_INFO:
				$this->getOutput()->addHTML(
					( new ChangeFileInfoForm( $this, $importPlan ) )->getHtml()
				);
				break;
			case self::ACTION_VIEW_DIFF:
				$this->getOutput()->addHTML(
					( new FileInfoDiffPage( $this, $importPlan ) )->getHtml()
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
	 * @param string $url
	 */
	private function handleImportException( ImportException $exception, $url ) {
		if ( $exception instanceof DuplicateFilesException ) {
				$this->getOutput()->addHTML( ( new DuplicateFilesErrorPage(
					$exception->getFiles(),
					$url
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

		$this->getOutput()->addHTML(
			( new ErrorPage( $this->getWarningMessage( $exception ), $url ) )->getHtml()
		);
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
		$sourceSite = $this->sourceSiteLocator->getSourceSite( $url );
		$importDetails = $sourceSite->retrieveImportDetails( $url );

		return $this->importPlanFactory->newPlan( $importRequest, $importDetails, $this->getUser() );
	}

	private function doImport( ImportPlan $importPlan ) {
		$out = $this->getOutput();
		$importDetails = $importPlan->getDetails();

		$importDetailsHash = $out->getRequest()->getVal( 'importDetailsHash', '' );
		$token = $out->getRequest()->getVal( 'token', '' );

		if ( !$this->getUser()->matchEditToken( $token ) ) {
			$this->showWarningMessage( wfMessage( 'fileimporter-badtoken' )->parse() );
			return false;
		}

		if ( $importDetails->getOriginalHash() !== $importDetailsHash ) {
			$this->showWarningMessage( wfMessage( 'fileimporter-badimporthash' )->parse() );
			return false;
		}

		try {
			$result = $this->importer->import(
				$this->getUser(),
				$importPlan
			);
		} catch ( ValidationException $exception ) {
			$this->showWarningMessage( $this->getWarningMessage( $exception ) );
			return false;
		}

		if ( $result ) {
			$successPage = new ImportSuccessPage( $importPlan );
			$out->setPageTitle( $successPage->getPageTitle() );
			$out->addHTML( $successPage->getHtml() );
		} else {
			$this->showWarningMessage( wfMessage( 'fileimporter-importfailed' )->parse() );
		}

		return $result;
	}

	/**
	 * @param Exception $e
	 *
	 * @return string HTML
	 */
	private function getWarningMessage( Exception $e ) {
		if ( $e instanceof ILocalizedException ) {
			return $e->getMessageObject()->parse();
		} else {
			return htmlspecialchars( $e->getMessage() );
		}
	}

	/**
	 * @param string $html
	 */
	private function showWarningMessage( $html ) {
		$this->getOutput()->addHTML(
			Html::rawElement(
				'div',
				[ 'class' => 'warningbox' ],
				Html::rawElement( 'p', [], $html )
			) .
			Html::rawElement( 'br' )
		);
	}

	private function showImportPage( ImportPlan $importPlan ) {
		$this->getOutput()->addHTML(
			( new ImportPreviewPage( $this, $importPlan ) )->getHtml()
		);
	}

	private function showLandingPage() {
		if ( $this->config->get( 'FileImporterInBeta' ) ) {
			$this->showWarningMessage( wfMessage( 'fileimporter-in-beta' )->parse() );
		}

		$page = $this->config->get( 'FileImporterShowInputScreen' )
			? new InputFormPage( $this )
			: new InfoPage( $this );

		$this->getOutput()->addHTML( $page->getHtml() );
	}

}
