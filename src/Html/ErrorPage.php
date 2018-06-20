<?php

namespace FileImporter\Html;

use Html;
use OOUI\ButtonWidget;
use Message;

/**
 * @license GPL-2.0-or-later
 * @author Andrew Kostka <andrew.kostka@wikimedia.de>
 */
class ErrorPage {

	/**
	 * @var string
	 */
	private $errorMessage;

	/**
	 * @var string|null
	 */
	private $url;

	/**
	 * @param string $errorMessage
	 * @param string|null $url
	 */
	public function __construct( $errorMessage, $url ) {
		$this->errorMessage = $errorMessage;
		$this->url = $url;
	}

	/**
	 * @return string
	 */
	public function getHtml() {
		$output = Html::rawElement(
			'div',
			[ 'class' => 'mw-importfile-error-banner errorbox' ],
			Html::rawElement( 'p', [], $this->errorMessage )
		);

		if ( $this->url !== null ) {
			$output .= new ButtonWidget(
				[
					'label' => ( new Message( 'fileimporter-go-to-original-file-button' ) )->plain(),
					'href' => $this->url,
					'classes' => [ 'mw-importfile-error-back-button' ],
					'flags' => [ 'primary', 'progressive' ]
				]
			);
		}

		return $output;
	}

}
