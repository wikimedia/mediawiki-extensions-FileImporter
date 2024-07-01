<?php

namespace FileImporter\Html;

use MediaWiki\Html\Html;

/**
 * Page displaying extension usage information.
 *
 * @license GPL-2.0-or-later
 */
class InfoPage extends SpecialPageHtmlFragment {

	public function getHtml(): string {
		return Html::rawElement(
			'p',
			[],
			$this->msg( 'fileimporter-input-page-info-text' )->parse()
		);
	}

}
