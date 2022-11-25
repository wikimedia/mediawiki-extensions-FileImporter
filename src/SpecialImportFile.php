<?php

namespace FileImporter;

use Config;
use EditPage;
use ErrorPageError;
use Exception;
use ExtensionRegistry;
use FileImporter\Data\ImportPlan;
use FileImporter\Data\ImportRequest;
use FileImporter\Exceptions\AbuseFilterWarningsException;
use FileImporter\Exceptions\CommunityPolicyException;
use FileImporter\Exceptions\DuplicateFilesException;
use FileImporter\Exceptions\ImportException;
use FileImporter\Exceptions\RecoverableTitleException;
use FileImporter\Html\ChangeFileInfoForm;
use FileImporter\Html\ChangeFileNameForm;
use FileImporter\Html\DuplicateFilesErrorPage;
use FileImporter\Html\ErrorPage;
use FileImporter\Html\FileInfoDiffPage;
use FileImporter\Html\HelpBanner;
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
use MediaWiki\Extension\GlobalBlocking\GlobalBlocking;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\User\UserOptionsManager;
use Message;
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

	/** @var SourceSiteLocator */
	private $sourceSiteLocator;
	/** @var Importer */
	private $importer;
	/** @var ImportPlanFactory */
	private $importPlanFactory;
	/** @var UserOptionsManager */
	private $userOptionsManager;
	/** @var StatsdDataFactoryInterface */
	private $stats;
	/** @var LoggerInterface */
	private $logger;

	/**
	 * @param SourceSiteLocator $sourceSiteLocator
	 * @param Importer $importer
	 * @param ImportPlanFactory $importPlanFactory
	 * @param StatsdDataFactoryInterface $statsdDataFactory
	 * @param UserOptionsManager $userOptionsManager
	 * @param Config $config
	 */
	public function __construct(
		SourceSiteLocator $sourceSiteLocator,
		Importer $importer,
		ImportPlanFactory $importPlanFactory,
		StatsdDataFactoryInterface $statsdDataFactory,
		UserOptionsManager $userOptionsManager,
		Config $config
	) {
		parent::__construct(
			'ImportFile',
			$config->get( 'FileImporterRequiredRight' ),
			$config->get( 'FileImporterShowInputScreen' )
		);

		$this->sourceSiteLocator = $sourceSiteLocator;
		$this->importer = $importer;
		$this->importPlanFactory = $importPlanFactory;
		$this->userOptionsManager = $userOptionsManager;
		$this->stats = $statsdDataFactory;
		$this->logger = LoggerFactory::getInstance( 'FileImporter' );
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
	 * Checks based on those in EditPage and SpecialUpload
	 *
	 * @throws ErrorPageError when one of the checks failed
	 */
	private function executeStandardChecks() {
		$unicodeCheck = $this->getRequest()->getText( 'wpUnicodeCheck' );
		if ( $unicodeCheck && $unicodeCheck !== EditPage::UNICODE_CHECK ) {
			throw new ErrorPageError( 'errorpagetitle', 'unicode-support-fail' );
		}

		# Check uploading enabled
		if ( !UploadBase::isEnabled() ) {
			$this->logErrorStats( self::ERROR_UPLOAD_DISABLED, false );
			throw new ErrorPageError( 'uploaddisabled', 'uploaddisabledtext' );
		}

		$user = $this->getUser();

		// Check if the user does have all the rights required via $wgFileImporterRequiredRight (set
		// to "upload" by default), as well as "upload" and "edit" in case …RequiredRight is more
		// relaxed. Note special pages must call userCanExecute() manually when parent::execute()
		// isn't called, {@see SpecialPage::__construct}.
		$missingPermission = parent::userCanExecute( $user )
			? UploadBase::isAllowed( $user )
			: $this->getRestriction();
		if ( is_string( $missingPermission ) ) {
			$this->logErrorStats( self::ERROR_USER_PERMISSIONS, false );
			throw new PermissionsError( $missingPermission );
		}

		# Check blocks
		$localBlock = $user->getBlock();
		if ( $localBlock ) {
			$this->logErrorStats( self::ERROR_LOCAL_BLOCK, false );
			throw new UserBlockedError( $localBlock );
		}

		// Global blocks
		if ( ExtensionRegistry::getInstance()->isLoaded( 'GlobalBlocking' ) ) {
			$globalBlock = GlobalBlocking::getUserBlock( $user, $this->getRequest()->getIP() );
			if ( $globalBlock ) {
				$this->logErrorStats( self::ERROR_GLOBAL_BLOCK, false );
				throw new UserBlockedError( $globalBlock );
			}
		}

		# Check whether we actually want to allow changing stuff
		$this->checkReadOnly();
	}

	/** @inheritDoc */
	public function getDescription() {
		return $this->msg( 'fileimporter-specialpage' )->text();
	}

	private function setupPage() {
		$output = $this->getOutput();
		$output->enableOOUI();
		$output->addModuleStyles( 'ext.FileImporter.SpecialCss' );
		$output->addModuleStyles( 'ext.FileImporter.Images' );
		$output->addModules( 'ext.FileImporter.SpecialJs' );
	}

	/**
	 * @param string|null $subPage
	 */
	public function execute( $subPage ) {
		$this->setHeaders();
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

		if ( $webRequest->getBool( HelpBanner::HIDE_HELP_BANNER_CHECK_BOX ) ) {
			$this->userOptionsManager->setOption(
				$this->getUser(),
				HelpBanner::HIDE_HELP_BANNER_PREFERENCE,
				'1'
			);
			$this->userOptionsManager->saveOptions( $this->getUser() );
		}

		try {
			$this->logger->info( 'Getting ImportPlan for URL: ' . $clientUrl );
			$importPlan = $this->makeImportPlan( $webRequest );

			$action = $webRequest->getRawVal( ImportPreviewPage::ACTION_BUTTON );
			if ( $action ) {
				$this->logger->info( "Performing $action on ImportPlan for URL: $clientUrl" );
			}
			$this->handleAction( $action, $importPlan );
		} catch ( ImportException $exception ) {
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
	private function makeImportPlan( WebRequest $webRequest ): ImportPlan {
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
		$importPlan->setValidationWarnings(
			json_decode( $webRequest->getVal( 'validationWarnings', '[]' ), true )
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
	private function doImport( ImportPlan $importPlan ): bool {
		$out = $this->getOutput();
		$importDetails = $importPlan->getDetails();

		$importDetailsHash = $out->getRequest()->getRawVal( 'importDetailsHash', '' );
		$token = $out->getRequest()->getRawVal( 'token', '' );

		if ( !$this->getContext()->getCsrfTokenSet()->matchToken( $token ) ) {
			$this->showWarningMessage( $this->msg( 'fileimporter-badtoken' )->parse() );
			$this->logErrorStats( 'badToken', true );
			return false;
		}

		if ( $importDetails->getOriginalHash() !== $importDetailsHash ) {
			$this->showWarningMessage( $this->msg( 'fileimporter-badimporthash' )->parse() );
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
					$this->getUser(),
					$postImportResult
				) );

			return true;
		} catch ( ImportException $exception ) {
			$this->logErrorStats(
				(string)$exception->getCode(),
				$exception instanceof RecoverableTitleException
			);

			if ( $exception instanceof AbuseFilterWarningsException ) {
				$this->showWarningMessage( $this->getWarningMessage( $exception ), 'warning' );

				foreach ( $exception->getMessages() as $msg ) {
					$this->showWarningMessage(
						Message::newFromSpecifier( $msg )->parse(),
						'warning',
						true
					);
				}

				$this->showImportPage( $importPlan );
			} else {
				$this->showWarningMessage(
					Html::rawElement( 'strong', [], $this->msg( 'fileimporter-importfailed' )->parse() ) .
					'<br>' .
					$this->getWarningMessage( $exception ),
					'error'
				);
			}
			return false;
		}
	}

	/**
	 * @param ImportPlan $importPlan
	 */
	private function logActionStats( ImportPlan $importPlan ): void {
		foreach ( $importPlan->getActionStats() as $key => $_ ) {
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
	private function getWarningMessage( Exception $ex ): string {
		if ( $ex instanceof ILocalizedException ) {
			return $ex->getMessageObject()->parse();
		}

		return htmlspecialchars( $ex->getMessage() );
	}

	/**
	 * @param string $html
	 * @param string $type Set to "notice" for a gray box, defaults to "error" (red)
	 * @param bool $inline
	 */
	private function showWarningMessage( string $html, string $type = 'error', $inline = false ) {
		$this->getOutput()->enableOOUI();
		$this->getOutput()->addHTML(
			new MessageWidget( [
				'label' => new HtmlSnippet( $html ),
				'type' => $type,
				'inline' => $inline,
			] ) .
			'<br>'
		);
	}

	/**
	 * @param ImportPlan $importPlan
	 */
	private function showImportPage( ImportPlan $importPlan ): void {
		$this->getOutput()->addHTML(
			( new ImportPreviewPage( $this ) )->getHtml( $importPlan )
		);
	}

	private function showLandingPage(): void {
		$page = $this->getConfig()->get( 'FileImporterShowInputScreen' )
			? new InputFormPage( $this )
			: new InfoPage( $this );

		$this->getOutput()->addHTML( $page->getHtml() );
	}

}
