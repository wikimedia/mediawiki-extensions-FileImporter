<?php

namespace FileImporter\Html;

use Html;

/**
 * Page displaying extension usage information.
 *
 * @license GPL-2.0-or-later
 */
class InfoPage extends SpecialPageHtmlFragment {

	/**
	 * @return string
	 */
	public function getHtml() {
		return Html::rawElement(
			'p',
			[],
			$this->msg( 'fileimporter-input-page-info-text' )->parse()
		);
	}

}
