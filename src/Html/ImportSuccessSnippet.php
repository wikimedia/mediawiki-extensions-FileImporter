<?php

namespace FileImporter\Html;

use FileImporter\Services\SuccessCache;
use Html;
use MediaWiki\MediaWikiServices;
use Message;
use MessageLocalizer;
use MessageSpecifier;
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
	 * @param MessageLocalizer $messageLocalizer
	 * @param Title $targetTitle Final local title of imported file
	 *
	 * @return string
	 */
	public function getHtml( MessageLocalizer $messageLocalizer, Title $targetTitle ) {
		$importResult = $this->cache->fetchImportResult( $targetTitle );
		if ( !$importResult || !$importResult->isOK() ) {
			return '';
		}

		/** @var string|array|MessageSpecifier $messageSpecifier */
		$messageSpecifier = $importResult->getValue();
		$msg = Message::newFromSpecifier( $messageSpecifier );
		$html = Html::successBox( $msg->parse() );

		$warnings = $importResult->getErrorsByType( 'warning' );
		foreach ( $warnings as $warning ) {
			$msg = $messageLocalizer->msg( $warning['message'], $warning['params'] ?? [] );
			$html .= Html::warningBox( $msg->parse() );
		}

		return Html::rawElement(
			'div',
			[ 'class' => 'mw-ext-fileimporter-noticebox' ],
			$html
		);
	}

}
