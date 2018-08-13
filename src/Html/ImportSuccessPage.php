<?php

namespace FileImporter\Html;

use FileImporter\Data\ImportPlan;
use FileImporter\Data\SourceUrl;
use OOUI\ButtonWidget;
use Html;
use Title;

/**
 * Page displaying a successful import.
 *
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class ImportSuccessPage {

	/**
	 * @var SourceUrl
	 */
	private $sourceUrl;

	/**
	 * @var Title
	 */
	private $importTitle;

	public function __construct(
		ImportPlan $importPlan
	) {
		$this->sourceUrl = $importPlan->getRequest()->getUrl();
		$this->importTitle = $importPlan->getTitle();
	}

	/**
	 * @return string
	 */
	public function getPageTitle() {
		return $this->importTitle->getPrefixedText();
	}

	/**
	 * @return string
	 */
	public function getHtml() {
		return Html::rawElement(
			'div',
			[ 'class' => 'mw-importfile-success-banner successbox' ],
			wfMessage(
				'fileimporter-imported-success-banner'
			)->rawParams(
				Html::element(
					'a',
					[ 'href' => $this->importTitle->getInternalURL() ],
					$this->importTitle->getPrefixedText()
				)
			)->escaped()
		) .
		Html::rawElement(
			'p',
			[],
			wfMessage( 'fileimporter-imported-change-template' )->parse()
		) .
		new ButtonWidget(
			[
				'classes' => [ 'mw-importfile-add-template-button' ],
				'label' => wfMessage( 'fileimporter-go-to-original-file-button' )->plain(),
				'href' => $this->sourceUrl->getUrl(),
				'flags' => [ 'primary', 'progressive' ],
			]
		);
	}

}
