<?php

namespace FileImporter\Html;

use FileImporter\Data\SourceUrl;
use FileImporter\Services\SuccessCache;
use FileImporter\Services\WikidataTemplateLookup;
use IContextSource;
use MediaWiki\MediaWikiServices;
use Html;
use Title;

/**
 * Informational block embedded at the top of page after a successful import.
 *
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class ImportSuccessSnippet {

	const NOTICE_URL_KEY = 'fileImporterSuccess';

	/** @var SuccessCache $cache */
	private $cache;
	/** @var WikidataTemplateLookup $templateLookup */
	private $templateLookup;

	public function __construct() {
		$this->cache = MediaWikiServices::getInstance()
			->getService( 'FileImporterSuccessCache' );
		$this->templateLookup = MediaWikiServices::getInstance()
			->getService( 'FileImporterTemplateLookup' );
	}

	/**
	 * Prepares an URL for redirect and stashes additional information for retrieval from that page.
	 *
	 * @param Title $targetTitle
	 * @param string $sourceUrl
	 * @return string Target file URL for redirect, including special parameter to show our notice.
	 */
	public function getRedirectWithNotice( Title $targetTitle, $sourceUrl ) {
		$this->cache->stashSourceUrl( $targetTitle, $sourceUrl );
		return $targetTitle->getInternalURL( [ self::NOTICE_URL_KEY => 1 ] );
	}

	/**
	 * @param IContextSource $context Localization provider
	 * @param Title $targetTitle Final local title of imported file
	 *
	 * @return string
	 */
	public function getHtml( IContextSource $context, Title $targetTitle ) {
		$sourceUrl = $this->cache->fetchSourceUrl( $targetTitle );
		if ( $sourceUrl === false ) {
			return '';
		}

		/** @var WikidataTemplateLookup $lookup */
		$templateName = $this->templateLookup->fetchNowCommonsLocalTitle( new SourceUrl( $sourceUrl ) );

		if ( $templateName ) {
			$instructions = $context->msg( 'fileimporter-add-specific-template' )
				->params( $sourceUrl, $templateName, $targetTitle )
				->parse();
		} else {
			$instructions = $context->msg( 'fileimporter-add-unknown-template' )
				->params( $sourceUrl )
				->parse();
		}

		return Html::rawElement(
			'div',
			[ 'class' => 'mw-importfile-success-banner' ],
			Html::successBox( $instructions )
		);
	}

}
