<?php

namespace FileImporter\Html;

use FileImporter\Services\SuccessCache;
use MediaWiki\Html\Html;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use MediaWiki\User\UserIdentity;
use MessageLocalizer;
use OOUI\HtmlSnippet;
use OOUI\MessageWidget;
use StatusValue;
use Wikimedia\Message\MessageSpecifier;

/**
 * Informational block embedded at the top of page after a successful import.
 *
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class ImportSuccessSnippet {

	public const NOTICE_URL_KEY = 'fileImporterSuccess';

	private SuccessCache $cache;

	public function __construct() {
		$this->cache = MediaWikiServices::getInstance()->getService( 'FileImporterSuccessCache' );
	}

	/**
	 * Prepares an URL for redirect and stashes additional information for retrieval from that page.
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
	 */
	public function getHtml( MessageLocalizer $messageLocalizer, Title $targetTitle, UserIdentity $user ): string {
		$importResult = $this->cache->fetchImportResult( $targetTitle, $user );
		// This can happen when the user reloads a URL that still contains fileImporterSuccess=1
		if ( !$importResult ) {
			return '';
		}

		$html = '';

		/** @var string|array|MessageSpecifier|null $spec */
		$spec = $importResult->getValue();
		if ( $spec ) {
			// This reimplements Message::newFromSpecifier, but that wouldn't allow us to reuse the
			// Language from the provided MessageLocalizer.
			$msg = $messageLocalizer->msg( ...is_array( $spec ) ? $spec : [ $spec ] );
			$html .= new MessageWidget( [
				'label' => new HtmlSnippet( $msg->parse() ),
				'type' => 'success',
			] );
		}
		foreach ( $importResult->getMessages( 'error' ) as $msg ) {
			$html .= new MessageWidget( [
				'label' => new HtmlSnippet( $messageLocalizer->msg( $msg )->parse() ),
				'type' => 'error'
			] );
		}
		foreach ( $importResult->getMessages( 'warning' ) as $msg ) {
			$html .= new MessageWidget( [
				'label' => new HtmlSnippet( $messageLocalizer->msg( $msg )->parse() ),
				'type' => 'warning',
			] );
		}

		return Html::rawElement( 'div', [ 'class' => 'mw-ext-fileimporter-noticebox' ], $html );
	}

}
