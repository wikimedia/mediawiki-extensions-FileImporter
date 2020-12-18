<?php

namespace FileImporter\Html;

use FileImporter\Services\SuccessCache;
use Html;
use MediaWiki\MediaWikiServices;
use MediaWiki\User\UserIdentity;
use Message;
use MessageLocalizer;
use MessageSpecifier;
use OOUI\HtmlSnippet;
use OOUI\MessageWidget;
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
	 * @param UserIdentity $user
	 * @param StatusValue $importResult
	 *
	 * @return string Target file URL for redirect, including special parameter to show our notice.
	 */
	public function getRedirectWithNotice( Title $targetTitle, UserIdentity $user, StatusValue $importResult ) {
		$this->cache->stashImportResult( $targetTitle, $user, $importResult );
		return $targetTitle->getInternalURL( [ self::NOTICE_URL_KEY => 1 ] );
	}

	/**
	 * @param MessageLocalizer $messageLocalizer
	 * @param Title $targetTitle Final local title of imported file
	 * @param UserIdentity $user
	 *
	 * @return string
	 */
	public function getHtml( MessageLocalizer $messageLocalizer, Title $targetTitle, UserIdentity $user ) {
		$importResult = $this->cache->fetchImportResult( $targetTitle, $user );
		// This can happen when the user reloads a URL that still contains fileImporterSuccess=1
		if ( !$importResult ) {
			return '';
		}

		$html = '';

		/** @var string|array|MessageSpecifier|null $messageSpecifier */
		$messageSpecifier = $importResult->getValue();
		if ( $messageSpecifier ) {
			$msg = Message::newFromSpecifier( $messageSpecifier );
			$html .= new MessageWidget( [
				'label' => new HtmlSnippet( $msg->parse() ),
				'type' => 'success',
			] );
		}

		foreach ( $importResult->getErrors() as $error ) {
			$msg = $messageLocalizer->msg( $error['message'], $error['params'] ?? [] );
			$html .= new MessageWidget( [
				'label' => new HtmlSnippet( $msg->parse() ),
				'type' => $error['type'] === 'error' ? 'error' : 'warning',
			] );
		}

		return Html::rawElement( 'div', [ 'class' => 'mw-ext-fileimporter-noticebox' ], $html );
	}

}
