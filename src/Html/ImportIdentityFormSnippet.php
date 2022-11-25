<?php

namespace FileImporter\Html;

use Html;

/**
 * Collection of input elements that are used to persist the request from page load to page load.
 *
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class ImportIdentityFormSnippet {

	/**
	 * @var array
	 */
	private $identityParts;

	private const IDENTITY_KEYS = [
		'clientUrl',
		'intendedFileName',
		'intendedRevisionSummary',
		'intendedWikitext',
		'actionStats',
		'validationWarnings',
		'importDetailsHash',
		'automateSourceWikiCleanup',
		'automateSourceWikiDelete'
	];

	/**
	 * @param array $identityParts Keys:
	 *     - clientUrl, as initial input by the user
	 *     - intendedFileName, either generated from the client URL or passed by the user
	 *     - importDetailsHash, generated from the first import request, to ensure we know what
	 *                          we are importing
	 */
	public function __construct( array $identityParts ) {
		$this->identityParts = $identityParts;
	}

	/**
	 * @return string
	 */
	public function getHtml() {
		$html = '';

		foreach ( self::IDENTITY_KEYS as $identityKey ) {
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
