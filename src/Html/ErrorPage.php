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
	 * @param string $type Either "error" (default), or "warning"
	 *
	 * @return string
	 */
	public function getHtml( $errorMessage, $url, $type = 'error' ) {
		$output = ( new HelpBanner( $this ) )->getHtml();
		$output .= Html::rawElement(
			'div',
			[ 'class' => "mw-importfile-error-banner ${type}box" ],
			Html::rawElement( 'p', [], $errorMessage )
		);

		if ( $url !== null ) {
			$output .= Html::rawElement( 'br' ) . new ButtonWidget(
				[
					'label' => $this->msg( 'fileimporter-go-to-original-file-button' )->plain(),
					'href' => $url,
					'classes' => [ 'mw-importfile-error-back-button' ],
					'flags' => [ 'primary', 'progressive' ]
				]
			);
		}

		return $output;
	}

}
