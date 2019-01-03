<?php

namespace FileImporter\Html;

use Html;
use OOUI\ButtonWidget;

/**
 * @license GPL-2.0-or-later
 * @author Andrew Kostka <andrew.kostka@wikimedia.de>
 */
class ErrorPage extends SpecialPageHtmlFragment {

	/**
	 * @param string $errorMessage HTML
	 * @param string|null $url
	 *
	 * @return string
	 */
	public function getHtml( $errorMessage, $url ) {
		$output = Html::rawElement(
			'div',
			[ 'class' => 'mw-importfile-error-banner errorbox' ],
			Html::rawElement( 'p', [], $errorMessage )
		);

		if ( $url !== null ) {
			$output .= new ButtonWidget(
				[
					'label' => wfMessage( 'fileimporter-go-to-original-file-button' )->plain(),
					'href' => $url,
					'classes' => [ 'mw-importfile-error-back-button' ],
					'flags' => [ 'primary', 'progressive' ]
				]
			);
		}

		return $output;
	}

}
