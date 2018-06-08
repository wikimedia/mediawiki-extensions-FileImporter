<?php

namespace FileImporter\Html;

use Html;
use Message;
use SpecialPage;

/**
 * Page displaying extension usage information.
 */
class InfoPage {

	/**
	 * @var SpecialPage
	 */
	private $specialPage;

	public function __construct( SpecialPage $specialPage ) {
		$this->specialPage = $specialPage;
	}

	/**
	 * @return string
	 */
	public function getHtml() {
		return Html::rawElement(
			'p',
			[],
			( new Message( 'fileimporter-input-page-info-text' ) )->parse()
		);
	}

}
