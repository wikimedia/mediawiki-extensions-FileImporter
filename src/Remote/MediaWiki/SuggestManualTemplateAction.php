<?php

namespace FileImporter\Remote\MediaWiki;

use FileImporter\Data\ImportPlan;
use FileImporter\Interfaces\PostImportHandler;
use FileImporter\Services\WikidataTemplateLookup;
use Message;
use StatusValue;
use User;

/**
 * Display an educated guess about how to correctly mark a source file as having been imported to
 * Commons.
 *
 * @license GPL-2.0-or-later
 */
class SuggestManualTemplateAction implements PostImportHandler {

	/**
	 * @var WikidataTemplateLookup
	 */
	private $templateLookup;

	/**
	 * @param WikidataTemplateLookup $templateLookup
	 */
	public function __construct( WikidataTemplateLookup $templateLookup ) {
		$this->templateLookup = $templateLookup;
	}

	/**
	 * @param ImportPlan $importPlan
	 * @param User $user
	 *
	 * @return StatusValue
	 */
	public function execute( ImportPlan $importPlan, User $user ) {
		$sourceUrl = $importPlan->getDetails()->getSourceUrl();
		$templateName = $this->templateLookup->fetchNowCommonsLocalTitle( $sourceUrl );

		if ( $templateName ) {
			return StatusValue::newGood(
				new Message(
					'fileimporter-add-specific-template',
					[
						$sourceUrl->getUrl(),
						$templateName,
						$importPlan->getTitleText()
					]
				)
			);
		}

		return StatusValue::newGood(
			new Message(
				'fileimporter-add-unknown-template',
				[ $sourceUrl->getUrl() ]
			)
		);
	}

}
