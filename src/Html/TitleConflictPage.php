<?php

namespace FileImporter\Html;

use FileImporter\Data\ImportPlan;
use Html;
use Message;
use SpecialPage;

/**
 * Html showing a title conflict error and ChangeTitleForm
 */
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
	private $importPlan;

	/**
	 * @param SpecialPage $specialPage
	 * @param ImportPlan $importPlan
	 * @param string $warningMessage
	 */
	public function __construct(
		SpecialPage $specialPage,
		ImportPlan $importPlan,
		$warningMessage
	) {
		$this->specialPage = $specialPage;
		$this->importPlan = $importPlan;
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
			$this->importPlan
		) )->getHtml();
	}

}
