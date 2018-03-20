<?php

namespace FileImporter\Html;

use FileImporter\Data\ImportPlan;
use FileImporter\Data\SourceUrl;
use Html;
use Message;
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
	public function getHtml() {
		return Html::rawElement(
			'span',
			[],
			( new Message(
				'fileimporter-imported',
				[
					Html::element(
						'a',
						[ 'href' => $this->sourceUrl->getUrl() ],
						$this->sourceUrl->getUrl()
					),
					Html::element(
						'a',
						[ 'href' => $this->importTitle->getInternalURL() ],
						$this->importTitle->getPrefixedText()
					) ]
			) )->plain()
		);
	}

}
