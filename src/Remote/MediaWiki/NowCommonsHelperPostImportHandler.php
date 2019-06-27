<?php

namespace FileImporter\Remote\MediaWiki;

use FileImporter\Data\ImportPlan;
use FileImporter\Data\SourceUrl;
use FileImporter\Interfaces\PostImportHandler;
use FileImporter\Services\WikidataTemplateLookup;
use Message;
use StatusValue;
use User;

class NowCommonsHelperPostImportHandler implements PostImportHandler {

	/**
	 * @var WikidataTemplateLookup
	 */
	private $templateLookup;

	/**
	 * @param WikidataTemplateLookup $templateLookup
	 */
	public function __construct(
		WikidataTemplateLookup $templateLookup
	) {
		$this->templateLookup = $templateLookup;
	}

	/**
	 * @param ImportPlan $importPlan
	 * @param User $user
	 * @return \StatusValue
	 */
	public function execute( ImportPlan $importPlan, User $user ) {
		/** @var SourceUrl $sourceUrl */
		$sourceUrl = $importPlan->getDetails()->getSourceUrl();
		/** @var string $templateName */
		$templateName = $this->templateLookup->fetchNowCommonsLocalTitle( $sourceUrl );
		/** @var string $targetTitle */
		$targetTitle = $importPlan->getTitleText();

		if ( $templateName ) {
			return StatusValue::newGood(
				new Message(
					'fileimporter-add-specific-template',
					[
						$sourceUrl->getUrl(),
						$templateName,
						$targetTitle
					]
				)
			);
		} else {
			return StatusValue::newGood(
				new Message(
					'fileimporter-add-unknown-template',
					[ $sourceUrl->getUrl() ]
				)
			);
		}
	}

}
