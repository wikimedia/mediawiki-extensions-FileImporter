<?php

namespace FileImporter\Html;

use FileImporter\Data\ImportPlan;
use MediaWiki\Html\Html;

/**
 * Collection of input elements that are used to persist the request from page load to page load.
 *
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class ImportIdentityFormSnippet {

	private array $identityParts;

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
	 * @param ImportPlan $importPlan
	 * @param string[] $exclude Field names to exclude from the identity
	 * @return self
	 */
	public static function newFromImportPlan( ImportPlan $importPlan, array $exclude = [] ): self {
		return new self( array_diff_key( [
			'clientUrl' => $importPlan->getRequest()->getUrl(),
			'intendedFileName' => $importPlan->getFileName(),
			'intendedRevisionSummary' => $importPlan->getRequest()->getIntendedSummary(),
			'intendedWikitext' => $importPlan->getFileInfoText(),
			'actionStats' => json_encode( $importPlan->getActionStats() ),
			'validationWarnings' => json_encode( $importPlan->getValidationWarnings() ),
			'importDetailsHash' => $importPlan->getRequest()->getImportDetailsHash(),
			'automateSourceWikiCleanup' => $importPlan->getAutomateSourceWikiCleanUp(),
			'automateSourceWikiDelete' => $importPlan->getAutomateSourceWikiDelete(),
		], array_flip( $exclude ) ) );
	}

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

	public function getHtml(): string {
		$html = '';

		foreach ( self::IDENTITY_KEYS as $identityKey ) {
			if ( array_key_exists( $identityKey, $this->identityParts ) ) {
				$html .= Html::hidden( $identityKey, $this->identityParts[$identityKey] );
			}
		}

		return $html;
	}

}
