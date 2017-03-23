<?php

namespace FileImporter\Html;

use FileImporter\Generic\Data\TargetUrl;
use Html;
use Message;
use Title;

class ImportSuccessPage {

	/**
	 * @var TargetUrl
	 */
	private $targetUrl;

	/**
	 * @var Title
	 */
	private $importTitle;

	public function __construct(
		TargetUrl $targetUrl,
		Title $importTitle
	) {
		$this->targetUrl = $targetUrl;
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
						[ 'href' => $this->targetUrl->getUrl() ],
						$this->targetUrl->getUrl()
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
