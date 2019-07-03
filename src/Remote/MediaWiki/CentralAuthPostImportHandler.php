<?php

namespace FileImporter\Remote\MediaWiki;

use FileImporter\Data\ImportPlan;
use FileImporter\Data\SourceUrl;
use FileImporter\Interfaces\PostImportHandler;
use FileImporter\Services\WikidataTemplateLookup;
use Message;
use MessageSpecifier;
use Psr\Log\LoggerInterface;
use StatusValue;
use User;

class CentralAuthPostImportHandler implements PostImportHandler {

	const ERROR_FAILED_SOURCE_EDIT = 'sourceEditFailed';

	/**
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * @var RemoteApiActionExecutor
	 */
	private $remoteAction;

	/**
	 * @var WikidataTemplateLookup
	 */
	private $templateLookup;

	/**
	 * @param RemoteApiActionExecutor $remoteAction
	 * @param WikidataTemplateLookup $templateLookup
	 * @param LoggerInterface $logger
	 */
	public function __construct(
		RemoteApiActionExecutor $remoteAction,
		WikidataTemplateLookup $templateLookup,
		LoggerInterface $logger
	) {
		$this->remoteAction = $remoteAction;
		$this->templateLookup = $templateLookup;
		$this->logger = $logger;
	}

	/**
	 * @param ImportPlan $importPlan
	 * @param User $user
	 * @return StatusValue
	 */
	public function execute( ImportPlan $importPlan, User $user ) {
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
		MessageSpecifier $warningMsg = null
	) {
		/** @var StatusValue $status */
		$status = ( new NowCommonsHelperPostImportHandler( $this->templateLookup ) )
			->execute( $importPlan, $user );
		if ( $warningMsg ) {
			$status->warning( $warningMsg );
		}
		return $status;
	}

	/**
	 * @param ImportPlan $importPlan
	 * @param User $user
	 * @return StatusValue
	 */
	private function addNowCommonsToSource( ImportPlan $importPlan, User $user ) {
		// the template is cached and must be available due to the preconditions
		$templateName = $this->templateLookup->fetchNowCommonsLocalTitle(
			$importPlan->getDetails()->getSourceUrl()
		);

		$sourceUrl = $importPlan->getDetails()->getSourceUrl();
		$summary = wfMessage( 'fileimporter-cleanup-summary' )
			->inLanguage( $importPlan->getDetails()->getPageLanguage() )->text();
		$text = "\n{{" . $templateName . '|' . $importPlan->getTitleText() . '}}';

		$result = $this->remoteAction->executeEditAction(
			$sourceUrl,
			$user,
			[
				'title' => $importPlan->getTitle()->getPrefixedText(),
				'appendtext' => $text,
				'summary' => $summary,
			]
		);

		if ( $result !== null ) {
			return $this->successMessage( $sourceUrl );
		} else {
			$this->logger->error( __METHOD__ . ' failed to do post import edit.' );

			return $this->manualTemplateFallback(
				$importPlan, $user, new Message( 'fileimporter-cleanup-failed' ) );
		}
	}

	/**
	 * @param ImportPlan $importPlan
	 * @param User $user
	 * @return StatusValue
	 */
	private function deleteSourceFile( ImportPlan $importPlan, User $user ) {
		$sourceUrl = $importPlan->getDetails()->getSourceUrl();
		$summary = wfMessage( 'fileimporter-delete-summary' )
			->inLanguage( $importPlan->getDetails()->getPageLanguage() )->text();

		$result = $this->remoteAction->executeDeleteAction(
			$sourceUrl,
			$user,
			[
				'title' => $importPlan->getTitle()->getPrefixedText(),
				'reason' => $summary,
			]
		);

		if ( $result !== null ) {
			return $this->successMessage( $sourceUrl );
		} else {
			$this->logger->error( __METHOD__ . ' failed to do post import delete.' );

			$status = $this->successMessage( $sourceUrl );
			$status->warning(
				'fileimporter-delete-failed',
				$sourceUrl->getHost(),
				$sourceUrl->getUrl() );
			return $status;
		}
	}

	private function successMessage( SourceUrl $sourceUrl ) {
		return StatusValue::newGood(
			new Message( 'fileimporter-imported-success-banner' )
		);
	}

}
