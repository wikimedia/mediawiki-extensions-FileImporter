<?php

namespace FileImporter;

use ErrorPageError;
use Exception;
use FileImporter\Data\ImportPlan;
use FileImporter\Data\ImportRequest;
use FileImporter\Exceptions\CommunityPolicyException;
use FileImporter\Exceptions\DuplicateFilesException;
use FileImporter\Exceptions\ImportException;
use FileImporter\Exceptions\RecoverableTitleException;
use FileImporter\Html\ChangeFileInfoForm;
use FileImporter\Html\ChangeFileNameForm;
use FileImporter\Html\DuplicateFilesErrorPage;
use FileImporter\Html\ErrorPage;
use FileImporter\Html\FileInfoDiffPage;
use FileImporter\Html\ImportPreviewPage;
use FileImporter\Html\ImportSuccessSnippet;
use FileImporter\Html\InfoPage;
use FileImporter\Html\InputFormPage;
use FileImporter\Html\RecoverableTitleExceptionPage;
use FileImporter\Html\SourceWikiCleanupSnippet;
use FileImporter\Services\Importer;
use FileImporter\Services\ImportPlanFactory;
use FileImporter\Services\SourceSiteLocator;
use Html;
use ILocalizedException;
use Liuggio\StatsdClient\Factory\StatsdDataFactoryInterface;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use OOUI\HtmlSnippet;
use OOUI\MessageWidget;
use PermissionsError;
use Psr\Log\LoggerInterface;
use SpecialPage;
use UploadBase;
use User;
use UserBlockedError;
use WebRequest;

/**
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class SpecialImportFile extends SpecialPage {

	private const ERROR_UPLOAD_DISABLED = 'uploadDisabled';
	private const ERROR_USER_PERMISSIONS = 'userPermissionsError';
	private const ERROR_LOCAL_BLOCK = 'userBlocked';
	private const ERROR_GLOBAL_BLOCK = 'userGloballyBlocked';

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

	public function doesWrites() {
		return true;
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
	 *
	 * @throws ErrorPageError when one of the checks failed
	 */
	private function executeStandardChecks() {
		# Check uploading enabled
		if ( !UploadBase::isEnabled() ) {
			$this->logErrorStats( self::ERROR_UPLOAD_DISABLED, false );
			throw new ErrorPageError( 'uploaddisabled', 'uploaddisabledtext' );
		}

		# Check permissions
		$user = $this->getUser();
		$permissionRequired = UploadBase::isAllowed( $user );
		if ( $permissionRequired !== true ) {
			$this->logErrorStats( self::ERROR_USER_PERMISSIONS, false );
			throw new PermissionsError( $permissionRequired );
		}

		# Check blocks
		if ( $user->isBlocked() ) {
			$this->logErrorStats( self::ERROR_LOCAL_BLOCK, false );
			throw new UserBlockedError( $user->getBlock() );
		}

		// Global blocks
		if ( $user->isBlockedGlobally() ) {
			$this->logErrorStats( self::ERROR_GLOBAL_BLOCK, false );
			throw new UserBlockedError( $user->getGlobalBlock() );
		}

		# Check whether we actually want to allow changing stuff
		$this->checkReadOnly();
	}

	private function setupPage() {
		$output = $this->getOutput();
		$output->setPageTitle( wfMessage( 'fileimporter-specialpage' ) );
		$output->enableOOUI();
		$output->addModuleStyles( 'ext.FileImporter.SpecialCss' );
		$output->addModules( 'ext.FileImporter.SpecialJs' );
	}

	/**
	 * @param string|null $subPage
	 */
	public function execute( $subPage ) {
		$this->setupPage();
		$this->executeStandardChecks();

		$webRequest = $this->getRequest();
		$clientUrl = $webRequest->getVal( 'clientUrl', '' );

		// Note: executions by users that don't have the rights to view the page etc will not be
		// shown in this metric as executeStandardChecks will have already kicked them out,
		$this->stats->increment( 'FileImporter.specialPage.execute.total' );
		// The importSource url parameter is added to requests from the FileExporter extension.
		if ( $webRequest->getRawVal( 'importSource' ) === 'FileExporter' ) {
			$this->stats->increment( 'FileImporter.specialPage.execute.fromFileExporter' );
		}

		if ( $clientUrl === '' ) {
			$this->stats->increment( 'FileImporter.specialPage.execute.noClientUrl' );
			$this->showLandingPage();
			return;
		}

		try {
			$this->logger->info( 'Getting ImportPlan for URL: ' . $clientUrl );
			$importPlan = $this->makeImportPlan( $webRequest );

			$action = $webRequest->getRawVal( ImportPreviewPage::ACTION_BUTTON );
			if ( $action ) {
				$this->logger->info( "Performing $action on ImportPlan for URL: $clientUrl" );
			}
			$this->handleAction( $action, $importPlan );
		}
		catch ( ImportException $exception ) {
			$this->logger->info( 'ImportException: ' . $exception->getMessage() );
			$this->logErrorStats(
				(string)$exception->getCode(),
				$exception instanceof RecoverableTitleException
			);

			if ( $exception instanceof DuplicateFilesException ) {
				$html = ( new DuplicateFilesErrorPage( $this ) )->getHtml(
					$exception->getFiles(),
					$clientUrl
				);
			} elseif ( $exception instanceof RecoverableTitleException ) {
				$html = ( new RecoverableTitleExceptionPage( $this ) )->getHtml( $exception );
			} else {
				$html = ( new ErrorPage( $this ) )->getHtml(
					$this->getWarningMessage( $exception ),
					$clientUrl,
					$exception instanceof CommunityPolicyException ? 'warning' : 'error'
				);
			}
			$this->getOutput()->enableOOUI();
			$this->getOutput()->addHTML( $html );
		}
	}

	/**
	 * @param string|null $action
	 * @param ImportPlan $importPlan
	 */
	private function handleAction( $action, ImportPlan $importPlan ) {
		switch ( $action ) {
			case ImportPreviewPage::ACTION_SUBMIT:
				$this->doImport( $importPlan );
				break;
			case ImportPreviewPage::ACTION_EDIT_TITLE:
				$importPlan->setActionIsPerformed( ImportPreviewPage::ACTION_EDIT_TITLE );
				$this->getOutput()->addHTML(
					( new ChangeFileNameForm( $this ) )->getHtml( $importPlan )
				);
				break;
			case ImportPreviewPage::ACTION_EDIT_INFO:
				$importPlan->setActionIsPerformed( ImportPreviewPage::ACTION_EDIT_INFO );
				$this->getOutput()->addHTML(
					( new ChangeFileInfoForm( $this ) )->getHtml( $importPlan )
				);
				break;
			case ImportPreviewPage::ACTION_VIEW_DIFF:
				$this->getOutput()->addHTML(
					( new FileInfoDiffPage( $this ) )->getHtml( $importPlan )
				);
				break;
			default:
				$this->showImportPage( $importPlan );
		}
	}

	/**
	 * @param WebRequest $webRequest
	 *
	 * @return ImportPlan
	 * @throws ImportException
	 */
	private function makeImportPlan( WebRequest $webRequest ) {
		$importRequest = new ImportRequest(
			$webRequest->getVal( 'clientUrl' ),
			$webRequest->getVal( 'intendedFileName' ),
			$webRequest->getVal( 'intendedWikitext' ),
			$webRequest->getVal( 'intendedRevisionSummary' ),
			$webRequest->getRawVal( 'importDetailsHash', '' )
		);

		$url = $importRequest->getUrl();
		$sourceSite = $this->sourceSiteLocator->getSourceSite( $url );
		$importDetails = $sourceSite->retrieveImportDetails( $url );

		$importPlan = $this->importPlanFactory->newPlan(
			$importRequest,
			$importDetails,
			$this->getUser()
		);
		$importPlan->setActionStats(
			json_decode( $webRequest->getVal( 'actionStats', '[]' ), true )
		);
		$importPlan->setAutomateSourceWikiCleanUp(
			$webRequest->getBool( 'automateSourceWikiCleanup' )
		);
		$importPlan->setAutomateSourceWikiDelete(
			$webRequest->getBool( 'automateSourceWikiDelete' )
		);

		return $importPlan;
	}

	/**
	 * @param string $type
	 * @param bool $isRecoverable
	 */
	private function logErrorStats( $type, $isRecoverable ) {
		$this->stats->increment( 'FileImporter.error.byRecoverable.'
			. wfBoolToStr( $isRecoverable ) . '.byType.' . $type );
	}

	/**
	 * @param ImportPlan $importPlan
	 * @return bool
	 */
	private function doImport( ImportPlan $importPlan ) {
		$out = $this->getOutput();
		$importDetails = $importPlan->getDetails();

		$importDetailsHash = $out->getRequest()->getRawVal( 'importDetailsHash', '' );
		$token = $out->getRequest()->getRawVal( 'token', '' );

		if ( !$this->getUser()->matchEditToken( $token ) ) {
			$this->showWarningMessage( wfMessage( 'fileimporter-badtoken' )->parse() );
			$this->logErrorStats( 'badToken', true );
			return false;
		}

		if ( $importDetails->getOriginalHash() !== $importDetailsHash ) {
			$this->showWarningMessage( wfMessage( 'fileimporter-badimporthash' )->parse() );
			$this->logErrorStats( 'badImportHash', true );
			return false;
		}

		try {
			$this->importer->import(
				$this->getUser(),
				$importPlan
			);
			$this->stats->increment( 'FileImporter.import.result.success' );
			$this->logActionStats( $importPlan );

			$postImportResult = $this->performPostImportActions( $importPlan );

			$out->redirect(
				( new ImportSuccessSnippet() )->getRedirectWithNotice(
					$importPlan->getTitle(),
					$postImportResult
				) );

			return true;
		} catch ( ImportException $exception ) {
			$this->logErrorStats(
				(string)$exception->getCode(),
				$exception instanceof RecoverableTitleException
			);

			$this->showWarningMessage( $this->getWarningMessage( $exception ) );
			$this->showWarningMessage( wfMessage( 'fileimporter-importfailed' )->parse() );

			return false;
		}
	}

	private function logActionStats( ImportPlan $importPlan ) {
		// currently the value should always be 1 is not really of interest here
		foreach ( $importPlan->getActionStats() as $key => $value ) {
			if ( $key === ImportPreviewPage::ACTION_EDIT_TITLE ||
				$key === ImportPreviewPage::ACTION_EDIT_INFO ||
				$key === SourceWikiCleanupSnippet::ACTION_OFFERED_SOURCE_DELETE ||
				$key === SourceWikiCleanupSnippet::ACTION_OFFERED_SOURCE_EDIT
			) {
				$this->stats->increment( 'FileImporter.specialPage.action.' . $key );
			}
		}
	}

	/**
	 * @param ImportPlan $importPlan
	 * @return \StatusValue
	 */
	private function performPostImportActions( ImportPlan $importPlan ) {
		$sourceSite = $importPlan->getRequest()->getUrl();
		$postImportHandler = $this->sourceSiteLocator->getSourceSite( $sourceSite )
			->getPostImportHandler();

		return $postImportHandler->execute( $importPlan, $this->getUser() );
	}

	/**
	 * @param Exception $ex
	 *
	 * @return string HTML
	 */
	private function getWarningMessage( Exception $ex ) {
		if ( $ex instanceof ILocalizedException ) {
			return $ex->getMessageObject()->parse();
		}

		return htmlspecialchars( $ex->getMessage() );
	}

	/**
	 * @param string $html
	 * @param string $type
	 */
	private function showWarningMessage( string $html, string $type = 'error' ) {
		$this->getOutput()->enableOOUI();
		$this->getOutput()->addHTML(
			new MessageWidget( [
				'label' => new HtmlSnippet( $html ),
				'type' => $type,
			] ) .
			Html::rawElement( 'br' )
		);
	}

	private function showImportPage( ImportPlan $importPlan ) {
		$this->getOutput()->addHTML(
			( new ImportPreviewPage( $this ) )->getHtml( $importPlan )
		);
	}

	private function showLandingPage() {
		if ( $this->config->get( 'FileImporterInBeta' ) ) {
			$this->showWarningMessage( wfMessage( 'fileimporter-in-beta' )->parse(), 'warning' );
		}

		$page = $this->config->get( 'FileImporterShowInputScreen' )
			? new InputFormPage( $this )
			: new InfoPage( $this );

		$this->getOutput()->addHTML( $page->getHtml() );
	}

}
