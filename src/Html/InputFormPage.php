<?php

namespace FileImporter\Html;

use Html;
use Message;
use OOUI\ButtonInputWidget;
use OOUI\TextInputWidget;
use SpecialPage;

/**
 * Page displaying a form for entering a URL to start an import.
 *
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class InputFormPage {

	/**
	 * @var SpecialPage
	 */
	private $specialPage;

	/**
	 * @param SpecialPage $specialPage
	 */
	public function __construct( SpecialPage $specialPage ) {
		$this->specialPage = $specialPage;
	}

	/**
	 * @return string
	 */
	public function getHtml() {
		return Html::openElement( 'div' ) .
			Html::openElement(
				'form',
				[
					'action' => $this->specialPage->getPageTitle()->getLocalURL(),
					'method' => 'POST',
				]
			) .
			new TextInputWidget(
				[
					'name' => 'clientUrl',
					'classes' => [ 'mw-fileimporter-url-text' ],
					'autofocus' => true,
					'required' => true,
					'type' => 'url',
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
