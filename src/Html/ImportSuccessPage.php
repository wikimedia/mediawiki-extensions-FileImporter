<?php

namespace FileImporter\Html;

use FileImporter\Data\ImportPlan;
use FileImporter\Data\SourceUrl;
use FileImporter\Services\WikidataTemplateLookup;
use MediaWiki\MediaWikiServices;
use OOUI\ButtonWidget;
use Html;

/**
 * Page displaying a successful import.
 *
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class ImportSuccessPage extends SpecialPageHtmlFragment {

	/**
	 * @param ImportPlan $importPlan
	 *
	 * @return string
	 */
	public function getHtml( ImportPlan $importPlan ) {
		$sourceUrl = $importPlan->getRequest()->getUrl();
		$importTitle = $importPlan->getTitle();
		$instructions = $this->buildSuggestedTemplateInstructions(
			$sourceUrl, $importTitle->getPrefixedText()
		);

		return Html::rawElement(
			'div',
			[ 'class' => 'mw-importfile-success-banner successbox' ],
			$this->msg(
				'fileimporter-imported-success-banner'
			)->rawParams(
				Html::element(
					'a',
					[ 'href' => $importTitle->getInternalURL() ],
					$importTitle->getPrefixedText()
				)
			)->escaped()
		) .
		Html::rawElement(
			'p',
			[],
			$instructions
		) .
		new ButtonWidget(
			[
				'classes' => [ 'mw-importfile-add-template-button' ],
				'label' => $this->msg( 'fileimporter-go-to-original-file-button' )->plain(),
				'href' => $sourceUrl->getUrl(),
				'flags' => [ 'primary', 'progressive' ],
			]
		);
	}

	private function buildSuggestedTemplateInstructions( SourceUrl $sourceUrl, $targetTitle ) {
		/** @var WikidataTemplateLookup $lookup */
		$lookup = MediaWikiServices::getInstance()->getService( 'FileImporterTemplateLookup' );
		$templateName = $lookup->fetchNowCommonsLocalTitle( $sourceUrl );

		if ( $templateName ) {
			return $this->msg( 'fileimporter-add-specific-template' )
				->params( $sourceUrl->getUrl(), $templateName, $targetTitle )
				->parse();
		} else {
			return $this->msg( 'fileimporter-add-unknown-template' )
				->params( $sourceUrl->getUrl() )
				->parse();
		}
	}

}
