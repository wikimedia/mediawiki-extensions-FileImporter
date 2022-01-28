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

		if ( $type === 'error' ) {
			$output .= Html::errorBox( $errorMessage, '', 'mw-importfile-error-banner' );
		} else {
			$output .= Html::warningBox( $errorMessage, 'mw-importfile-error-banner' );
		}

		if ( $url !== null ) {
			$output .= '<br>' . new ButtonWidget(
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
