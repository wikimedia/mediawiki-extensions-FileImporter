<?php

namespace FileImporter\Html;

use FileImporter\Data\ImportPlan;
use Html;
use MediaWiki\Widget\TitleInputWidget;
use Message;
use OOUI\ButtonInputWidget;
use SpecialPage;

/**
 * Form allowing the user to select a new title.
 */
class ChangeTitleForm {

	/**
	 * @var SpecialPage
	 */
	private $specialPage;

	/**
	 * @var ImportPlan
	 */
	private $importPlan;

	public function __construct( SpecialPage $specialPage, ImportPlan $importPlan ) {
		$this->specialPage = $specialPage;
		$this->importPlan = $importPlan;
	}

	public function getHtml() {
		return Html::openElement(
			'form',
			[
				'action' => $this->specialPage->getPageTitle()->getLocalURL(),
				'method' => 'POST',
			]
		) .
		Html::element(
			'p',
			[],
			( new Message( 'fileimporter-newtitle' ) )->plain()
		) .
		new TitleInputWidget(
			[
				'name' => 'intendedFileName',
				'value' => $this->importPlan->getFileName(),
				'classes' => [ 'mw-importfile-import-newtitle' ],
				'placeholder' => ( new Message( 'fileimporter-editsummary-placeholder' ) )->plain(),
				'suggestions' => false,
			]
		) .
		( new ImportIdentityFormSnippet( [
			'clientUrl' => $this->importPlan->getRequest()->getUrl(),
			'importDetailsHash' => $this->specialPage->getRequest()->getVal( 'importDetailsHash' ),
		] ) )->getHtml() .
		new ButtonInputWidget(
			[
				'label' => ( new Message( 'fileimporter-submit' ) )->plain(),
				'type' => 'submit',
				'flags' => [ 'primary', 'progressive' ],
			]
		) .
		Html::closeElement( 'form' );
	}

}
