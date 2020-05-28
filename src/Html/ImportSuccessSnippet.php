<?php

namespace FileImporter\Html;

use FileImporter\Services\SuccessCache;
use Html;
use IContextSource;
use MediaWiki\MediaWikiServices;
use Message;
use StatusValue;
use Title;

/**
 * Informational block embedded at the top of page after a successful import.
 *
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class ImportSuccessSnippet {

	public const NOTICE_URL_KEY = 'fileImporterSuccess';

	/** @var SuccessCache */
	private $cache;

	public function __construct() {
		$this->cache = MediaWikiServices::getInstance()->getService( 'FileImporterSuccessCache' );
	}

	/**
	 * Prepares an URL for redirect and stashes additional information for retrieval from that page.
	 *
	 * @param Title $targetTitle
	 * @param StatusValue $importResult
	 *
	 * @return string Target file URL for redirect, including special parameter to show our notice.
	 */
	public function getRedirectWithNotice( Title $targetTitle, StatusValue $importResult ) {
		$this->cache->stashImportResult( $targetTitle, $importResult );
		return $targetTitle->getInternalURL( [ self::NOTICE_URL_KEY => 1 ] );
	}

	/**
	 * @param IContextSource $context Localization provider
	 * @param Title $targetTitle Final local title of imported file
	 *
	 * @return string
	 */
	public function getHtml( IContextSource $context, Title $targetTitle ) {
		$importResult = $this->cache->fetchImportResult( $targetTitle );
		if ( !$importResult || !$importResult->isOK() ) {
			return '';
		}

		/** @var string|string[]|MessageSpecifier $statusMessage */
		$statusMessage = $importResult->getValue();

		$html = Html::successBox( $context->msg( $statusMessage )->parse() );

		$warnings = $importResult->getErrorsByType( 'warning' );
		foreach ( $warnings as $warning ) {
			$warningMessage = new Message( $warning['message'], $warning['params'] );
			$html .= Html::warningBox( $context->msg( $warningMessage )->parse() );
		}

		return Html::rawElement(
			'div',
			[ 'class' => 'mw-ext-fileimporter-noticebox' ],
			$html
		);
	}

}
