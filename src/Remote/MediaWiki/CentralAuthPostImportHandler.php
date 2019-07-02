<?php

namespace FileImporter\Remote\MediaWiki;

use FileImporter\Data\ImportPlan;
use FileImporter\Interfaces\PostImportHandler;
use FileImporter\Services\WikidataTemplateLookup;
use Message;
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
		if ( $importPlan->getAutomateSourceWikiCleanUp() ) {
			return $this->addNowCommonsToSource( $importPlan, $user );
		}

		$fallbackPostImport = new NowCommonsHelperPostImportHandler( $this->templateLookup );
		return $fallbackPostImport->execute( $importPlan, $user );
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
		$text = '{{' . $templateName . '|' . $importPlan->getTitleText() . '}}';

		$result = $this->remoteAction->executeEditAction(
			$sourceUrl,
			$user,
			[
				'title' => $importPlan->getTitle()->getPrefixedText(),
				'appendtext' => $text,
				'summary' => $summary,
			]
		);

		if ( $result === null ) {
			$this->logger->error( __METHOD__ . ' failed to do post import edit.' );

			$fallbackPostImport = new NowCommonsHelperPostImportHandler( $this->templateLookup );
			$fallbackStatus = $fallbackPostImport->execute( $importPlan, $user );
			$fallbackStatus->warning( 'fileimporter-cleanup-failed' );
			return $fallbackStatus;
		}

		return StatusValue::newGood(
			new Message(
				'fileimporter-imported-success-banner',
				[ $sourceUrl->getUrl() ]
			)
		);
	}

}
