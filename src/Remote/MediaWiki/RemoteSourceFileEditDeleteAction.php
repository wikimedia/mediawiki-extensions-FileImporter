<?php

namespace FileImporter\Remote\MediaWiki;

use FileImporter\Data\ImportPlan;
use FileImporter\Interfaces\PostImportHandler;
use FileImporter\Services\WikidataTemplateLookup;
use MediaWiki\User\User;
use MediaWiki\Utils\UrlUtils;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use StatusValue;
use Wikimedia\Stats\Metrics\CounterMetric;
use Wikimedia\Stats\StatsFactory;

/**
 * Delete the source file, or edit to add the {{NowCommons}} template.
 *
 * @license GPL-2.0-or-later
 */
class RemoteSourceFileEditDeleteAction implements PostImportHandler {

	private PostImportHandler $fallbackHandler;
	private WikidataTemplateLookup $templateLookup;
	private RemoteApiActionExecutor $remoteAction;
	private UrlUtils $urlUtils;
	private LoggerInterface $logger;
	private CounterMetric $postImportCounter;

	public function __construct(
		PostImportHandler $fallbackHandler,
		WikidataTemplateLookup $templateLookup,
		RemoteApiActionExecutor $remoteAction,
		UrlUtils $urlUtils,
		?LoggerInterface $logger = null,
		?StatsFactory $statsFactory = null
	) {
		$this->fallbackHandler = $fallbackHandler;
		$this->templateLookup = $templateLookup;
		$this->remoteAction = $remoteAction;
		$this->urlUtils = $urlUtils;
		$this->logger = $logger ?? new NullLogger();
		$statsFactory ??= StatsFactory::newNull();
		$this->postImportCounter = $statsFactory->getCounter( 'FileImporter_postImport_results_total' );
	}

	/**
	 * @inheritDoc
	 */
	public function execute( ImportPlan $importPlan, User $user ): StatusValue {
		if ( $importPlan->getAutomateSourceWikiDelete() ) {
			return $this->deleteSourceFile( $importPlan, $user );
		} elseif ( $importPlan->getAutomateSourceWikiCleanUp() ) {
			return $this->addNowCommonsToSource( $importPlan, $user );
		} else {
			// Note this may also be triggered if the above methods fail.
			return $this->manualTemplateFallback( $importPlan, $user );
		}
	}

	private function manualTemplateFallback(
		ImportPlan $importPlan,
		User $user,
		?string $warningMsg = null
	): StatusValue {
		$status = $this->fallbackHandler->execute( $importPlan, $user );
		if ( $warningMsg ) {
			$status->warning( $warningMsg );
		}
		return $status;
	}

	private function addNowCommonsToSource( ImportPlan $importPlan, User $user ): StatusValue {
		$templateName = $this->templateLookup->fetchNowCommonsLocalTitle(
			$importPlan->getDetails()->getSourceUrl()
		);
		// This should be unreachable because the user can't click the checkbox in this case. But we
		// know this from a POST request, which might be altered or simply outdated.
		// Note: This intentionally doesn't allow a template with the name "0".
		if ( !$templateName ) {
			return $this->successMessage();
		}

		$sourceUrl = $importPlan->getDetails()->getSourceUrl();
		$summary = wfMessage(
			'fileimporter-cleanup-summary',
			$this->urlUtils->expandIRI( $importPlan->getTitle()->getFullURL( '', false, PROTO_CANONICAL ) ) ?? ''
		)->inLanguage( $importPlan->getDetails()->getPageLanguage() )->text();
		$text = "\n{{" . wfEscapeWikiText( $templateName ) . '|' .
			wfEscapeWikiText( $importPlan->getTitle()->getText() ) . '}}';

		$status = $this->remoteAction->executeEditAction(
			$sourceUrl,
			$user,
			$importPlan->getOriginalTitle()->getPrefixedText(),
			[ 'appendtext' => $text ],
			$summary
		);

		if ( $status->isGood() ) {
			$this->postImportCounter->setLabel( 'result', 'success' )
				->setLabel( 'action', 'edit' )
				->copyToStatsdAt( 'FileImporter.import.postImport.edit.successful' )
				->increment();
			return $this->successMessage();
		} else {
			$this->logger->error( __METHOD__ . ' failed to do post import edit.' );
			$this->postImportCounter->setLabel( 'result', 'failed' )
				->setLabel( 'action', 'edit' )
				->copyToStatsdAt( 'FileImporter.import.postImport.edit.failed' )
				->increment();

			return $this->manualTemplateFallback(
				$importPlan,
				$user,
				'fileimporter-cleanup-failed'
			);
		}
	}

	private function deleteSourceFile( ImportPlan $importPlan, User $user ): StatusValue {
		$sourceUrl = $importPlan->getDetails()->getSourceUrl();
		$summary = wfMessage(
			'fileimporter-delete-summary',
			$this->urlUtils->expandIRI( $importPlan->getTitle()->getFullURL( '', false, PROTO_CANONICAL ) ) ?? ''
		)->inLanguage( $importPlan->getDetails()->getPageLanguage() )->text();

		$status = $this->remoteAction->executeDeleteAction(
			$sourceUrl,
			$user,
			$importPlan->getOriginalTitle()->getPrefixedText(),
			$summary
		);

		if ( $status->isGood() ) {
			$this->postImportCounter->setLabel( 'result', 'success' )
				->setLabel( 'action', 'delete' )
				->copyToStatsdAt( 'FileImporter.import.postImport.delete.successful' )
				->increment();
			return $this->successMessage();
		} else {
			$this->logger->error( __METHOD__ . ' failed to do post import delete.' );
			$this->postImportCounter->setLabel( 'result', 'failed' )
				->setLabel( 'action', 'delete' )
				->copyToStatsdAt( 'FileImporter.import.postImport.delete.failed' )
				->increment();

			$status = $this->successMessage();
			$status->warning(
				'fileimporter-delete-failed',
				$sourceUrl->getHost(),
				$sourceUrl->getUrl()
			);
			return $status;
		}
	}

	private function successMessage(): StatusValue {
		return StatusValue::newGood( 'fileimporter-imported-success-banner' );
	}

}
