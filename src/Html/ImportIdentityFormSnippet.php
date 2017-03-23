<?php

namespace FileImporter\Html;

use Html;

class ImportIdentityFormSnippet {

	/**
	 * @var string[]
	 */
	private $identityParts;

	private static $identityKeys = [
		'clientUrl',
		'intendedTitle',
		'importDetailsHash',
	];

	/**
	 * @param string[] $identityParts Keys:
	 *     - clientUrl, as initial input by the user
	 *     - intendedTitle, either generated from the client URL or passed by the user
	 *     - importDetailsHash, generated from the first import request, to ensure we know what
	 *                          we are importing
	 */
	public function __construct( array $identityParts ) {
		$this->identityParts = $identityParts;
	}

	public function getHtml() {
		$html = '';

		foreach ( self::$identityKeys as $identityKey ) {
			if ( array_key_exists( $identityKey, $this->identityParts ) ) {
				$html .= Html::element(
					'input',
					[
						'type' => 'hidden',
						'name' => $identityKey,
						'value' => $this->identityParts[$identityKey],
					]
				);
			}
		}

		return $html;
	}

}
