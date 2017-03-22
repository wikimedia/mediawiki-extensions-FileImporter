<?php

namespace FileImporter\Html;

use FileImporter\Generic\Data\TargetUrl;
use Html;
use Message;
use OOUI\ButtonInputWidget;
use OOUI\TextInputWidget;
use SpecialPage;

/**
 * Page displaying a form for entering a URL to start an import.
 */
class InputFormPage {

	/**
	 * @var SpecialPage
	 */
	private $specialPage;

	/**
	 * @var TargetUrl|null
	 */
	private $targetUrl;

	/**
	 * @param SpecialPage $specialPage
	 * @param TargetUrl|null $targetUrl
	 */
	public function __construct(
		SpecialPage $specialPage,
		TargetUrl $targetUrl = null
	) {
		$this->specialPage = $specialPage;
		$this->targetUrl = $targetUrl;
	}

	/**
	 * @return string
	 */
	public function getHtml() {
		return
			Html::openElement( 'div' ) .
			Html::openElement(
			'form',
			[
				'action' => $this->specialPage->getPageTitle()->getLocalURL(),
				'method' => 'GET',
			]
		) .
			new TextInputWidget(
			[
				'name' => 'clientUrl',
				'classes' => [ 'mw-fileimporter-url-text' ],
				'autofocus' => true,
				'required' => true,
				'type' => 'url',
				'value' => $this->targetUrl ? $this->targetUrl->getUrl() : '',
				'placeholder' => ( new Message( 'fileimporter-exampleprefix' ) )->plain() .
					': https://en.wikipedia.org/wiki/File:Berlin_Skyline',
			]
		) .
			new ButtonInputWidget(
			[
				'classes' => [ 'mw-fileimporter-url-submit' ],
				'label' => ( new Message( 'fileimporter-submit' ) )->plain(),
				'type' => 'submit',
				'flags' => [ 'primary', 'progressive' ],
			]
		) .
			Html::closeElement( 'form' ) .
			Html::closeElement( 'div' );
	}

}
