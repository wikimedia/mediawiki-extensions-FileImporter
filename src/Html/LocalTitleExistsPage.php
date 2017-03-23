<?php

namespace FileImporter\Html;

use FileImporter\Generic\Data\TargetUrl;
use Html;
use Message;
use SpecialPage;
use Title;

class LocalTitleExistsPage {

	/**
	 * @var SpecialPage
	 */
	private $specialPage;

	/**
	 * @var TargetUrl
	 */
	private $targetUrl;

	/**
	 * @var Title
	 */
	private $title;

	public function __construct( SpecialPage $specialPage, TargetUrl $targetUrl, Title $title ) {
		$this->specialPage = $specialPage;
		$this->targetUrl = $targetUrl;
		$this->title = $title;
	}

	public function getHtml() {
		return Html::rawElement(
			'div',
			[ 'class' => 'warningbox' ],
			Html::element( 'p', [], ( new Message( 'fileimporter-localtitleexists' ) )->plain() )
		) .
		( new ChangeTitleForm(
			$this->specialPage,
			$this->targetUrl,
			$this->title
		) )->getHtml();
	}

}
