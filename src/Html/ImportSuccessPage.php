<?php

namespace FileImporter\Html;

use FileImporter\Data\ImportPlan;
use FileImporter\Data\SourceUrl;
use MediaWiki\MediaWikiServices;
use OOUI\ButtonWidget;
use Html;
use Message;
use Title;

/**
 * Page displaying a successful import.
 *
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class ImportSuccessPage {

	/**
	 * @var SourceUrl
	 */
	private $sourceUrl;

	/**
	 * @var Title
	 */
	private $importTitle;

	public function __construct(
		ImportPlan $importPlan
	) {
		$this->sourceUrl = $importPlan->getRequest()->getUrl();
		$this->importTitle = $importPlan->getTitle();
	}

	/**
	 * @return string
	 */
	public function getPageTitle() {
		return $this->importTitle->getPrefixedText();
	}

	/**
	 * @return string
	 */
	public function getHtml() {
		$config = MediaWikiServices::getInstance()->getMainConfig();
		$siteName = $config->get( 'Sitename' );

		return Html::rawElement(
			'div',
			[ 'class' => 'mw-importfile-success-banner successbox' ],
			( new Message(
				'fileimporter-imported-success-banner',
				[
					$siteName,
					Html::element(
						'a',
						[ 'href' => $this->importTitle->getInternalURL() ],
						$this->importTitle->getPrefixedText()
					)
				]
			) )->plain()
		) .
		Html::rawElement(
			'p',
			[],
			( new Message(
				'fileimporter-imported-change-template',
				[ $siteName ]
			) )->plain()
		) .
		new ButtonWidget(
			[
				'classes' => [ 'mw-importfile-add-template-button' ],
				'label' => ( new Message( 'fileimporter-add-template-button' ) )->plain(),
				'href' => $this->sourceUrl->getUrl(),
				'flags' => [ 'primary', 'progressive' ],
			]
		);
	}
}
