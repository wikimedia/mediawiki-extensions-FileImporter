<?php

namespace FileImporter\Html;

use FileImporter\Exceptions\RecoverableTitleException;
use Html;
use SpecialPage;

/**
 * Html showing an error and the ChangeTitleForm
 */
class RecoverableTitleExceptionPage {

	/**
	 * @var SpecialPage
	 */
	private $specialPage;

	/**
	 * @var RecoverableTitleException
	 */
	private $exception;

	/**
	 * @param SpecialPage $specialPage
	 * @param RecoverableTitleException $exception
	 */
	public function __construct(
		SpecialPage $specialPage,
		RecoverableTitleException $exception
	) {
		$this->specialPage = $specialPage;
		$this->exception = $exception;
	}

	/**
	 * @return string
	 */
	private function getMessageString() {
		return $this->exception
			->getMessageObject()
			->plain();
	}

	/**
	 * @return string
	 */
	public function getHtml() {
		return Html::rawElement(
			'div',
			[ 'class' => 'warningbox' ],
			Html::element( 'p', [], ( $this->getMessageString() ) )
		) .
		( new ChangeFileNameForm(
			$this->specialPage,
			$this->exception->getImportPlan()
		) )->getHtml();
	}

}
