<?php

namespace FileImporter\Remote\MediaWiki;

use FileImporter\Data\ImportPlan;
use FileImporter\Interfaces\PostImportHandler;
use FileImporter\Services\WikidataTemplateLookup;
use IBufferingStatsdDataFactory;
use NullStatsdDataFactory;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use StatusValue;
use User;

/**
 * Delete the source file, or edit to add the {{NowCommons}} template.
 *
 * @license GPL-2.0-or-later
 */
class RemoteSourceFileEditDeleteAction implements PostImportHandler {

	private const STATSD_SOURCE_DELETE_FAIL = 'FileImporter.import.postImport.delete.failed';
	private const STATSD_SOURCE_DELETE_SUCCESS = 'FileImporter.import.postImport.delete.successful';
	private const STATSD_SOURCE_EDIT_FAIL = 'FileImporter.import.postImport.edit.failed';
	private const STATSD_SOURCE_EDIT_SUCCESS = 'FileImporter.import.postImport.edit.successful';

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
	 * @param LoggerInterface|null $logger
	 * @param IBufferingStatsdDataFactory|null $statsd
	 */
	public function __construct(
		PostImportHandler $fallbackHandler,
		WikidataTemplateLookup $templateLookup,
		RemoteApiActionExecutor $remoteAction,
		LoggerInterface $logger = null,
		IBufferingStatsdDataFactory $statsd = null
	) {
		$this->fallbackHandler = $fallbackHandler;
		$this->templateLookup = $templateLookup;
		$this->remoteAction = $remoteAction;
		$this->logger = $logger ?: new NullLogger();
		$this->statsd = $statsd ?: new NullStatsdDataFactory();
	}

	/**
	 * @inheritDoc
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
		string $warningMsg = null
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
			wfExpandIRI( $importPlan->getTitle()->getFullURL( '', false, PROTO_CANONICAL ) )
		)->inLanguage( $importPlan->getDetails()->getPageLanguage() )->text();
		$text = "\n{{" . wfEscapeWikiText( $templateName ) . '|' .
			wfEscapeWikiText( $importPlan->getTitle()->getText() ) . '}}';

		$status = $this->remoteAction->executeEditAction(
			$sourceUrl,
			$user,
			$importPlan->getOriginalTitle()->getPrefixedText(),
			[
				'appendtext' => $text,
			],
			$summary
		);

		if ( $status->isGood() ) {
			$this->statsd->increment( self::STATSD_SOURCE_EDIT_SUCCESS );
			return $this->successMessage();
		} else {
			$this->logger->error( __METHOD__ . ' failed to do post import edit.' );
			$this->statsd->increment( self::STATSD_SOURCE_EDIT_FAIL );

			return $this->manualTemplateFallback(
				$importPlan,
				$user,
				'fileimporter-cleanup-failed'
			);
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
			wfExpandIRI( $importPlan->getTitle()->getFullURL( '', false, PROTO_CANONICAL ) )
		)->inLanguage( $importPlan->getDetails()->getPageLanguage() )->text();

		$status = $this->remoteAction->executeDeleteAction(
			$sourceUrl,
			$user,
			$importPlan->getOriginalTitle()->getPrefixedText(),
			$summary
		);

		if ( $status->isGood() ) {
			$this->statsd->increment( self::STATSD_SOURCE_DELETE_SUCCESS );
			return $this->successMessage();
		} else {
			$this->logger->error( __METHOD__ . ' failed to do post import delete.' );
			$this->statsd->increment( self::STATSD_SOURCE_DELETE_FAIL );

			$status = $this->successMessage();
			$status->warning(
				'fileimporter-delete-failed',
				$sourceUrl->getHost(),
				$sourceUrl->getUrl()
			);
			return $status;
		}
	}

	private function successMessage() {
		return StatusValue::newGood( 'fileimporter-imported-success-banner' );
	}

}
