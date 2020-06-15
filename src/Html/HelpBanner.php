<?php

namespace FileImporter\Html;

use FileImporter\FileImporterUtils;
use Html;
use MediaWiki\MediaWikiServices;
use MediaWiki\User\UserOptionsLookup;
use OOUI\HtmlSnippet;
use OOUI\IconWidget;
use OOUI\MessageWidget;

/**
 * @license GPL-2.0-or-later
 * @author Andrew Kostka <andrew.kostka@wikimedia.de>
 */
class HelpBanner extends SpecialPageHtmlFragment {

	public const HIDE_HELP_BANNER_PREFERENCE = 'userjs-fileimporter-hide-help-banner';
	public const HIDE_HELP_BANNER_CHECK_BOX = 'mw-importfile-disable-help-banner';

	/**
	 * @return bool
	 */
	private function shouldHelpBannerBeShown() {
		// TODO: Inject
		/** @var UserOptionsLookup $userOptionsLookup */
		$userOptionsLookup = MediaWikiServices::getInstance()->getService( 'UserOptionsLookup' );
		return !$userOptionsLookup->getBoolOption( $this->getUser(), self::HIDE_HELP_BANNER_PREFERENCE );
	}

	/**
	 * @return string
	 */
	public function getHtml() {
		if ( !$this->shouldHelpBannerBeShown() ) {
			return '';
		}

		$textSection = Html::rawElement(
			'div',
			[ 'class' => 'mw-importfile-help-banner-text' ],
			FileImporterUtils::addTargetBlankToLinks(
				$this->msg( 'fileimporter-help-banner-text' )->parse()
			)
		);

		$imageSection = Html::element(
			'div',
			[ 'class' => 'mw-importfile-image-help-banner' ],
			''
		);

		$closeSection = Html::rawElement(
			'label',
			[ 'for' => self::HIDE_HELP_BANNER_CHECK_BOX ],
			new IconWidget( [
				'icon' => 'close',
				'title' => $this->msg( 'fileimporter-help-banner-close-tooltip' )->text()
			] )
		);

		return Html::rawElement(
			'div',
			[ 'class' => 'mw-importfile-help-banner' ],
			Html::check(
				'mw-importfile-disable-help-banner',
				false,
				[ 'id' => self::HIDE_HELP_BANNER_CHECK_BOX ]
			) .
			new MessageWidget( [
				'label' => new HtmlSnippet(
					$textSection .
					$imageSection .
					$closeSection
				)
			] )
		);
	}

}
