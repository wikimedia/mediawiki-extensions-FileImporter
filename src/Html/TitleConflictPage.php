<?php

namespace FileImporter\Html;

use FileImporter\Data\SourceUrl;
use Html;
use Message;
use SpecialPage;
use Title;

class TitleConflictPage {

	/**
	 * @var SpecialPage
	 */
	private $specialPage;

	/**
	 * @var SourceUrl
	 */
	private $sourceUrl;

	/**
	 * @var Title
	 */
	private $title;

	/**
	 * @var string
	 */
	private $warningMessage;

	/**
	 * @param SpecialPage $specialPage
	 * @param SourceUrl $sourceUrl
	 * @param Title $title
	 * @param string $warningMessage
	 */
	public function __construct(
		SpecialPage $specialPage,
		SourceUrl $sourceUrl,
		Title $title,
		$warningMessage
	) {
		$this->specialPage = $specialPage;
		$this->sourceUrl = $sourceUrl;
		$this->title = $title;
		$this->warningMessage = $warningMessage;
	}

	public function getHtml() {
		return Html::rawElement(
			'div',
			[ 'class' => 'warningbox' ],
			Html::element( 'p', [], ( new Message( $this->warningMessage ) )->plain() )
		) .
		( new ChangeTitleForm(
			$this->specialPage,
			$this->sourceUrl,
			$this->title
		) )->getHtml();
	}

}
