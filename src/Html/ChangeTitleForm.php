<?php

namespace FileImporter\Html;

use FileImporter\Generic\Data\TargetUrl;
use Html;
use MediaWiki\Widget\TitleInputWidget;
use Message;
use OOUI\ButtonInputWidget;
use SpecialPage;
use Title;

class ChangeTitleForm {

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
				'value' => pathinfo( $this->title->getText() )['filename'],
				'classes' => [ 'mw-importfile-import-newtitle' ],
				'placeholder' => ( new Message( 'fileimporter-editsummary-placeholder' ) )->plain(),
				'suggestions' => false,
			]
		) .
		( new ImportIdentityFormSnippet( [
			'clientUrl' => $this->targetUrl->getUrl(),
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
