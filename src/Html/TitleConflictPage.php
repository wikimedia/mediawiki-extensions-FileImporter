<?php

namespace FileImporter\Html;

use FileImporter\Data\ImportPlan;
use Html;
use Message;
use SpecialPage;

class TitleConflictPage {

	/**
	 * @var SpecialPage
	 */
	private $specialPage;

	/**
	 * @var string
	 */
	private $warningMessage;

	/**
	 * @var ImportPlan
	 */
	private $plan;

	/**
	 * @param SpecialPage $specialPage
	 * @param ImportPlan $plan
	 * @param string $warningMessage
	 */
	public function __construct(
		SpecialPage $specialPage,
		ImportPlan $plan,
		$warningMessage
	) {
		$this->specialPage = $specialPage;
		$this->plan = $plan;
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
			$this->plan
		) )->getHtml();
	}

}
