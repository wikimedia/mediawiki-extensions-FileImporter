<?php

namespace FileImporter\Remote\MediaWiki;

use FileImporter\Data\ImportPlan;
use FileImporter\Interfaces\PostImportHandler;
use FileImporter\Services\WikidataTemplateLookup;
use IBufferingStatsdDataFactory;
use Message;
use MessageSpecifier;
use Psr\Log\LoggerInterface;
use StatusValue;
use User;

class CentralAuthPostImportHandler implements PostImportHandler {

	const STATSD_SOURCE_WIKI_DELETE_FAIL = 'FileImporter.import.postImport.delete.failed';
	const STATSD_SOURCE_WIKI_DELETE_SUCCESS = 'FileImporter.import.postImport.delete.successful';
	const STATSD_SOURCE_WIKI_EDIT_FAIL = 'FileImporter.import.postImport.edit.failed';
	const STATSD_SOURCE_WIKI_EDIT_SUCCESS = 'FileImporter.import.postImport.edit.successful';

	/**
	 * @var PostImportHandler
	 */
	private $fallbackHandler;

	/**
	 * @var WikidataTemplateLookup
	 */
	private $templateLookup;

	/**
	 * @var RemoteApiActionExecutor
	 */
	private $remoteAction;

	/**
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * @var IBufferingStatsdDataFactory
	 */
	private $statsd;

	/**
	 * @param PostImportHandler $fallbackHandler
	 * @param WikidataTemplateLookup $templateLookup
	 * @param RemoteApiActionExecutor $remoteAction
	 * @param LoggerInterface $logger
	 * @param IBufferingStatsdDataFactory $statsd
	 */
	public function __construct(
		PostImportHandler $fallbackHandler,
		WikidataTemplateLookup $templateLookup,
		RemoteApiActionExecutor $remoteAction,
		LoggerInterface $logger,
		IBufferingStatsdDataFactory $statsd
	) {
		$this->fallbackHandler = $fallbackHandler;
		$this->templateLookup = $templateLookup;
		$this->remoteAction = $remoteAction;
		$this->logger = $logger;
		$this->statsd = $statsd;
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
		$status = $this->fallbackHandler->execute( $importPlan, $user );
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
		$summary = wfMessage(
			'fileimporter-cleanup-summary',
			$importPlan->getTitle()->getFullURL( '', false, PROTO_CANONICAL )
		)->inLanguage( $importPlan->getDetails()->getPageLanguage() )->text();
		$text = "\n{{" . wfEscapeWikiText( $templateName ) . '|' .
			wfEscapeWikiText( $importPlan->getTitleText() ) . '}}';

		$result = $this->remoteAction->executeEditAction(
			$sourceUrl,
			$user,
			[
				'title' => $importPlan->getOriginalTitle()->getPrefixedText(),
				'appendtext' => $text,
				'summary' => $summary,
			]
		);

		if ( $result !== null ) {
			$this->statsd->increment( self::STATSD_SOURCE_WIKI_EDIT_SUCCESS );
			return $this->successMessage();
		} else {
			$this->logger->error( __METHOD__ . ' failed to do post import edit.' );
			$this->statsd->increment( self::STATSD_SOURCE_WIKI_EDIT_FAIL );

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
		$summary = wfMessage(
			'fileimporter-delete-summary',
			$importPlan->getTitle()->getFullURL( '', false, PROTO_CANONICAL )
		)->inLanguage( $importPlan->getDetails()->getPageLanguage() )->text();

		$result = $this->remoteAction->executeDeleteAction(
			$sourceUrl,
			$user,
			[
				'title' => $importPlan->getOriginalTitle()->getPrefixedText(),
				'reason' => $summary,
			]
		);

		if ( $result !== null ) {
			$this->statsd->increment( self::STATSD_SOURCE_WIKI_DELETE_SUCCESS );
			return $this->successMessage();
		} else {
			$this->logger->error( __METHOD__ . ' failed to do post import delete.' );
			$this->statsd->increment( self::STATSD_SOURCE_WIKI_DELETE_FAIL );

			$status = $this->successMessage();
			$status->warning(
				'fileimporter-delete-failed',
				$sourceUrl->getHost(),
				$sourceUrl->getUrl() );
			return $status;
		}
	}

	private function successMessage() {
		return StatusValue::newGood(
			new Message( 'fileimporter-imported-success-banner' )
		);
	}

}
