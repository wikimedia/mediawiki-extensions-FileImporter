<?php

namespace FileImporter;

use ErrorPageError;
use Exception;
use ExtensionRegistry;
use FileImporter\Data\ImportPlan;
use FileImporter\Data\ImportRequest;
use FileImporter\Exceptions\AbuseFilterWarningsException;
use FileImporter\Exceptions\CommunityPolicyException;
use FileImporter\Exceptions\DuplicateFilesException;
use FileImporter\Exceptions\HttpRequestException;
use FileImporter\Exceptions\ImportException;
use FileImporter\Exceptions\LocalizedImportException;
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
use FileImporter\Remote\MediaWiki\RemoteApiActionExecutor;
use FileImporter\Services\Importer;
use FileImporter\Services\ImportPlanFactory;
use FileImporter\Services\SourceSiteLocator;
use FileImporter\Services\WikidataTemplateLookup;
use Liuggio\StatsdClient\Factory\StatsdDataFactoryInterface;
use MediaWiki\Config\Config;
use MediaWiki\Content\IContentHandlerFactory;
use MediaWiki\EditPage\EditPage;
use MediaWiki\Html\Html;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Request\WebRequest;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Status\Status;
use MediaWiki\User\Options\UserOptionsManager;
use MediaWiki\User\User;
use OOUI\HtmlSnippet;
use OOUI\MessageWidget;
use PermissionsError;
use Psr\Log\LoggerInterface;
use StatusValue;
use UploadBase;
use UserBlockedError;

/**
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class SpecialImportFile extends SpecialPage {

	private const ERROR_UPLOAD_DISABLED = 'uploadDisabled';
	private const ERROR_USER_PERMISSIONS = 'userPermissionsError';
	private const ERROR_LOCAL_BLOCK = 'userBlocked';

	private SourceSiteLocator $sourceSiteLocator;
	private Importer $importer;
	private ImportPlanFactory $importPlanFactory;
	private RemoteApiActionExecutor $remoteActionApi;
	private WikidataTemplateLookup $templateLookup;
	private IContentHandlerFactory $contentHandlerFactory;
	private StatsdDataFactoryInterface $stats;
	private UserOptionsManager $userOptionsManager;
	private LoggerInterface $logger;

	public function __construct(
		SourceSiteLocator $sourceSiteLocator,
		Importer $importer,
		ImportPlanFactory $importPlanFactory,
		RemoteApiActionExecutor $remoteActionApi,
		WikidataTemplateLookup $templateLookup,
		IContentHandlerFactory $contentHandlerFactory,
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
		$this->remoteActionApi = $remoteActionApi;
		$this->templateLookup = $templateLookup;
		$this->contentHandlerFactory = $contentHandlerFactory;
		$this->stats = $statsdDataFactory;
		$this->userOptionsManager = $userOptionsManager;
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

		# Check whether we actually want to allow changing stuff
		$this->checkReadOnly();
	}

	/** @inheritDoc */
	public function getDescription() {
		return $this->msg( 'fileimporter-specialpage' );
	}

	/**
	 * @param string|null $subPage
	 */
	public function execute( $subPage ): void {
		$webRequest = $this->getRequest();
		$clientUrl = $webRequest->getVal( 'clientUrl', '' );
		$action = $webRequest->getRawVal( ImportPreviewPage::ACTION_BUTTON );
		if ( $action ) {
			$this->logger->info( "Performing $action on ImportPlan for URL: $clientUrl" );
		}

		$isCodex = $webRequest->getBool( 'codex' ) &&
			$this->getConfig()->get( 'FileImporterCodexMode' );
		$isCodexSubmit = $isCodex && $this->getRequest()->wasPosted() && $action === 'submit';

		if ( !$isCodexSubmit ) {
			$this->setHeaders();
			$this->getOutput()->enableOOUI();
		}
		$this->executeStandardChecks();

		if ( !$isCodex ) {
			$this->getOutput()->addModuleStyles( 'ext.FileImporter.SpecialCss' );
			$this->getOutput()->addModuleStyles( 'ext.FileImporter.Images' );
			$this->getOutput()->addModules( 'ext.FileImporter.SpecialJs' );
		}

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

		if ( $webRequest->getBool( HelpBanner::HIDE_HELP_BANNER_CHECK_BOX ) &&
			$this->getUser()->isNamed()
		) {
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

			if ( $isCodexSubmit ) {
				// disable all default output of the special page, like headers, title, navigation
				$this->getOutput()->disable();
				header( 'Content-type: application/json; charset=utf-8' );
				$this->doCodexImport( $importPlan );
			} elseif ( $isCodex ) {
				$this->getOutput()->addModules( 'ext.FileImporter.SpecialCodexJs' );
				$this->showCodexImportPage( $importPlan );
			} else {
				$this->handleAction( $action, $importPlan );
			}
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

	private function handleAction( ?string $action, ImportPlan $importPlan ): void {
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
				$contentHandler = $this->contentHandlerFactory->getContentHandler( CONTENT_MODEL_WIKITEXT );
				$this->getOutput()->addHTML(
					( new FileInfoDiffPage( $this ) )->getHtml( $importPlan, $contentHandler )
				);
				break;
			default:
				$this->showImportPage( $importPlan );
		}
	}

	/**
	 * @throws ImportException
	 */
	private function makeImportPlan( WebRequest $webRequest ): ImportPlan {
		$importRequest = new ImportRequest(
			$webRequest->getVal( 'clientUrl' ),
			$webRequest->getVal( 'intendedFileName' ),
			$webRequest->getVal( 'intendedWikitext' ),
			$webRequest->getVal( 'intendedRevisionSummary' ),
			$webRequest->getRawVal( 'importDetailsHash' ) ?? ''
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

	private function logErrorStats( string $type, bool $isRecoverable ): void {
		$this->stats->increment( 'FileImporter.error.byRecoverable.'
			. wfBoolToStr( $isRecoverable ) . '.byType.' . $type );
	}

	private function doCodexImport( ImportPlan $importPlan ): void {
		// TODO handle error cases and echo JSON to allow Codex to visualize the errors
		try {
			$this->importer->import(
				$this->getUser(),
				$importPlan
			);
			$this->stats->increment( 'FileImporter.import.result.success' );
			$this->logActionStats( $importPlan );

			$postImportResult = $this->performPostImportActions( $importPlan );
			$successRedirectUrl = ( new ImportSuccessSnippet() )->getRedirectWithNotice(
				$importPlan->getTitle(),
				$this->getUser(),
				$postImportResult
			);

			echo json_encode( [
				'success' => true,
				'redirect' => $successRedirectUrl,
			] );
		} catch ( ImportException $exception ) {
			if ( $exception instanceof AbuseFilterWarningsException ) {
				$warningMessages = [];
				$warningMessages[] = [
					'type' => 'warning',
					'message' => $this->getWarningMessage( $exception )
				];

				foreach ( $exception->getMessages() as $msg ) {
					$warningMessages[] = [
						'type' => 'warning',
						'message' => $this->msg( $msg )->parse()
					];
				}

				echo json_encode( [
					'error' => true,
					'warningMessages' => $warningMessages,
					'validationWarnings' => $importPlan->getValidationWarnings()
				] );
			} else {
				// TODO: More graceful error handling
				echo json_encode( [
					'error' => true,
					'output' => $exception->getTrace(),
				] );
			}
		}
	}

	private function doImport( ImportPlan $importPlan ): bool {
		$out = $this->getOutput();
		$importDetails = $importPlan->getDetails();

		$importDetailsHash = $out->getRequest()->getRawVal( 'importDetailsHash' ) ?? '';
		$token = $out->getRequest()->getRawVal( 'token' ) ?? '';

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
			// TODO: inline at site of action
			$this->logActionStats( $importPlan );

			$postImportResult = $this->performPostImportActions( $importPlan );

			$out->redirect(
				( new ImportSuccessSnippet() )->getRedirectWithNotice(
					$importPlan->getTitle(),
					$this->getUser(),
					$postImportResult
				)
			);

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
						$this->msg( $msg )->parse(),
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

	private function logActionStats( ImportPlan $importPlan ): void {
		foreach ( $importPlan->getActionStats() as $key => $_ ) {
			if (
				$key === ImportPreviewPage::ACTION_EDIT_TITLE ||
				$key === ImportPreviewPage::ACTION_EDIT_INFO ||
				$key === SourceWikiCleanupSnippet::ACTION_OFFERED_SOURCE_DELETE ||
				$key === SourceWikiCleanupSnippet::ACTION_OFFERED_SOURCE_EDIT
			) {
				$this->stats->increment( 'FileImporter.specialPage.action.' . $key );
			}
		}
	}

	private function performPostImportActions( ImportPlan $importPlan ): StatusValue {
		$sourceSite = $importPlan->getRequest()->getUrl();
		$postImportHandler = $this->sourceSiteLocator->getSourceSite( $sourceSite )
			->getPostImportHandler();

		return $postImportHandler->execute( $importPlan, $this->getUser() );
	}

	/**
	 * @return string HTML
	 */
	private function getWarningMessage( Exception $ex ): string {
		if ( $ex instanceof LocalizedImportException ) {
			return $ex->getMessageObject()->inLanguage( $this->getLanguage() )->parse();
		}
		if ( $ex instanceof HttpRequestException ) {
			return Status::wrap( $ex->getStatusValue() )->getHTML( false, false,
				$this->getLanguage() );
		}

		return htmlspecialchars( $ex->getMessage() );
	}

	/**
	 * @param string $html
	 * @param string $type Set to "notice" for a gray box, defaults to "error" (red)
	 * @param bool $inline
	 */
	private function showWarningMessage( string $html, string $type = 'error', bool $inline = false ): void {
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

	private function showImportPage( ImportPlan $importPlan ): void {
		$this->getOutput()->addHTML(
			( new ImportPreviewPage( $this ) )->getHtml( $importPlan )
		);
	}

	/**
	 * @return array of automation features and whether they are available
	 */
	private function getAutomatedCapabilities( ImportPlan $importPlan ) {
		$capabilities = [];

		$config = $this->getConfig();
		$isCentralAuthEnabled = ExtensionRegistry::getInstance()->isLoaded( 'CentralAuth' );
		$sourceUrl = $importPlan->getRequest()->getUrl();

		$capabilities['canAutomateEdit'] =
			$isCentralAuthEnabled &&
			$config->get( 'FileImporterSourceWikiTemplating' ) &&
			$this->templateLookup->fetchNowCommonsLocalTitle( $sourceUrl ) &&
			$this->remoteActionApi->executeTestEditActionQuery(
				$sourceUrl,
				$this->getUser(),
				$importPlan->getTitle()
			)->isGood();
		$capabilities['canAutomateDelete'] =
			$isCentralAuthEnabled &&
			$config->get( 'FileImporterSourceWikiDeletion' ) &&
			$this->remoteActionApi->executeUserRightsQuery( $sourceUrl, $this->getUser() )->isGood();

		if ( $capabilities['canAutomateDelete'] ) {
			$capabilities['automateDeleteSelected'] = $importPlan->getAutomateSourceWikiDelete();
			$this->stats->increment( 'FileImporter.specialPage.action.offeredSourceDelete' );
		} elseif ( $capabilities['canAutomateEdit'] ) {
			$capabilities['automateEditSelected'] =
				$importPlan->getAutomateSourceWikiCleanUp() ||
				$importPlan->getRequest()->getImportDetailsHash() === '';
			$capabilities['cleanupTitle'] =
				$this->templateLookup->fetchNowCommonsLocalTitle( $sourceUrl );
			$this->stats->increment( 'FileImporter.specialPage.action.offeredSourceEdit' );
		}

		return $capabilities;
	}

	private function showCodexImportPage( ImportPlan $importPlan ): void {
		$this->getOutput()->addHTML(
			Html::rawElement( 'noscript', [], $this->msg( 'fileimporter-no-script-warning' ) )
		);

		$this->getOutput()->addHTML(
			Html::rawElement( 'div', [ 'id' => 'ext-fileimporter-vue-root' ] )
		);

		$showHelpBanner = !$this->userOptionsManager
			->getBoolOption( $this->getUser(), 'userjs-fileimporter-hide-help-banner' );

		$this->getOutput()->addJsConfigVars( [
			'wgFileImporterAutomatedCapabilities' => $this->getAutomatedCapabilities( $importPlan ),
			'wgFileImporterClientUrl' => $importPlan->getRequest()->getUrl()->getUrl(),
			'wgFileImporterEditToken' => $this->getUser()->getEditToken(),
			'wgFileImporterFileRevisionsCount' =>
				count( $importPlan->getDetails()->getFileRevisions()->toArray() ),
			'wgFileImporterHelpBannerContentHtml' => $showHelpBanner ?
				FileImporterUtils::addTargetBlankToLinks(
					$this->msg( 'fileimporter-help-banner-text' )->parse()
				) : null,
			'wgFileImporterTextRevisionsCount' =>
				count( $importPlan->getDetails()->getTextRevisions()->toArray() ),
			'wgFileImporterTitle' => $importPlan->getFileName(),
			'wgFileImporterFileExtension' => $importPlan->getFileExtension(),
			'wgFileImporterPrefixedTitle' => $importPlan->getTitle()->getPrefixedText(),
			'wgFileImporterImageUrl' => $importPlan->getDetails()->getImageDisplayUrl(),
			'wgFileImporterInitialFileInfoWikitext' => $importPlan->getInitialFileInfoText(),
			'wgFileImporterFileInfoWikitext' =>
				// FIXME: can assume the edit field is persistent
				$importPlan->getRequest()->getIntendedText() ?? $importPlan->getFileInfoText(),
			'wgFileImporterEditSummary' => $importPlan->getRequest()->getIntendedSummary(),
			'wgFileImporterDetailsHash' => $importPlan->getDetails()->getOriginalHash(),
			'wgFileImporterTemplateReplacementCount' => $importPlan->getNumberOfTemplateReplacements(),
		] );
	}

	private function showLandingPage(): void {
		$page = $this->getConfig()->get( 'FileImporterShowInputScreen' )
			? new InputFormPage( $this )
			: new InfoPage( $this );

		$this->getOutput()->addHTML( $page->getHtml() );
	}

}
