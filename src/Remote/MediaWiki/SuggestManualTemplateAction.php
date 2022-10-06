<?php

namespace FileImporter\Remote\MediaWiki;

use FileImporter\Data\ImportPlan;
use FileImporter\Interfaces\PostImportHandler;
use FileImporter\Services\WikidataTemplateLookup;
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
	 * @return StatusValue Always good, i.e. never contains warnings. The status's value is a
	 *  message specifier explaining the suggested manual action.
	 */
	public function execute( ImportPlan $importPlan, User $user ): StatusValue {
		$sourceUrl = $importPlan->getDetails()->getSourceUrl();
		$templateName = $this->templateLookup->fetchNowCommonsLocalTitle( $sourceUrl );

		if ( $templateName ) {
			return StatusValue::newGood( [
				'fileimporter-add-specific-template',
				$sourceUrl->getUrl(),
				$templateName,
				$importPlan->getTitle()->getText()
			] );
		}

		return StatusValue::newGood( [
			'fileimporter-add-unknown-template',
			$sourceUrl->getUrl()
		] );
	}

}
