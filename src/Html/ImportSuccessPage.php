<?php

namespace FileImporter\Html;

use FileImporter\Data\SourceUrl;
use Html;
use Message;
use Title;

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
		SourceUrl $sourceUrl,
		Title $importTitle
	) {
		$this->sourceUrl = $sourceUrl;
		$this->importTitle = $importTitle;
	}

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
