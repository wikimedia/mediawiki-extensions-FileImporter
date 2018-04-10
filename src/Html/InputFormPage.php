<?php

namespace FileImporter\Html;

use EditPage;
use Html;
use OOUI\ButtonInputWidget;
use OOUI\TextInputWidget;

/**
 * Page displaying a form for entering a URL to start an import.
 *
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class InputFormPage extends SpecialPageHtmlFragment {

	/**
	 * @return string
	 */
	public function getHtml() {
		return Html::openElement( 'div' ) .
			Html::openElement(
				'form',
				[
					'action' => $this->getPageTitle()->getLocalURL(),
					'method' => 'POST',
				]
			) .
			Html::hidden( 'wpUnicodeCheck', EditPage::UNICODE_CHECK ) .
			( new HelpBanner( $this ) )->getHtml() .
			new TextInputWidget(
				[
					'name' => 'clientUrl',
					'autofocus' => true,
					'required' => true,
					'type' => 'url',
					'placeholder' => $this->msg( 'fileimporter-exampleprefix' )->plain() .
						': https://en.wikipedia.org/wiki/File:Berlin_Skyline',
				]
			) .
			new ButtonInputWidget(
				[
					'classes' => [ 'mw-fileimporter-url-submit' ],
					'label' => $this->msg( 'fileimporter-submit' )->plain(),
					'type' => 'submit',
					'flags' => [ 'primary', 'progressive' ],
				]
			) .
			Html::closeElement( 'form' ) .
			Html::closeElement( 'div' );
	}

}
