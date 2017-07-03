<?php

namespace FileImporter\Html;

use FileImporter\Data\ImportPlan;
use Html;
use MediaWiki\Widget\TitleInputWidget;
use Message;
use OOUI\ButtonInputWidget;
use SpecialPage;

class ChangeTitleForm {

	/**
	 * @var SpecialPage
	 */
	private $specialPage;

	/**
	 * @var ImportPlan
	 */
	private $plan;

	public function __construct( SpecialPage $specialPage, ImportPlan $plan ) {
		$this->specialPage = $specialPage;
		$this->plan = $plan;
	}

	public function getHtml() {
		return Html::openElement(
			'form',
			[
				'action' => $this->specialPage->getPageTitle()->getLocalURL(),
				'method' => 'GET',
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
				'value' => $this->plan->getFileName(),
				'classes' => [ 'mw-importfile-import-newtitle' ],
				'placeholder' => ( new Message( 'fileimporter-editsummary-placeholder' ) )->plain(),
				'suggestions' => false,
			]
		) .
		( new ImportIdentityFormSnippet( [
			'clientUrl' => $this->plan->getRequest()->getUrl(),
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
