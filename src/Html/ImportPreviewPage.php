<?php

namespace FileImporter\Html;

use FileImporter\Generic\Data\ImportDetails;
use Html;
use Linker;
use Message;
use OOUI\ButtonInputWidget;
use OOUI\TextInputWidget;
use SpecialPage;

/**
 * Page displaying the preview of the import before it has happened.
 * TODO This page can be used to make alterations
 */
class ImportPreviewPage {

	/**
	 * @var SpecialPage
	 */
	private $specialPage;

	/**
	 * @var ImportDetails
	 */
	private $importDetails;

	/**
	 * @param SpecialPage $specialPage
	 * @param ImportDetails $importDetails
	 */
	public function __construct(
		SpecialPage $specialPage,
		ImportDetails $importDetails
	) {
		$this->specialPage = $specialPage;
		$this->importDetails = $importDetails;
	}

	/**
	 * @return string
	 */
	public function getHtml() {
		$importDetails = $this->importDetails;
		$targetUrl = $importDetails->getTargetUrl();

		return
		Html::element(
			'p',
			[],
			( new Message( 'fileimporter-previewnote' ) )->parse()
		) .
		Html::element(
			'h2',
			[],
			$importDetails->getTitleText()
		) .
		Linker::makeExternalImage(
			$importDetails->getImageDisplayUrl(),
			$importDetails->getTitleText()
		) .
		Html::element(
			'h2',
			[],
			( new Message( 'fileimporter-heading-fileinfo' ) )->plain()
		) .
		Html::element(
			'h2',
			[],
			( new Message( 'fileimporter-heading-filehistory' ) )->plain()
		) .
		Html::element(
			'p',
			[],
			( new Message(
				'fileimporter-textrevisions',
				[ count( $importDetails->getTextRevisions() ) ]
			) )->parse()
		) .
		Html::openElement(
			'div',
			[ 'class' => 'mw-importfile-importOptions' ]
		) .
		Html::openElement(
			'form',
			[
				'action' => $this->specialPage->getPageTitle()->getLocalURL(),
				'method' => 'POST',
			]
		) .
		Html::element(
			'p',
			[],
			( new Message( 'fileimporter-editsummary' ) )->plain()
		) .
		new TextInputWidget(
			[
				'classes' => [ 'mw-importfile-import-summary' ],
				'placeholder' => ( new Message( 'fileimporter-editsummary-placeholder' ) )->plain(),
			]
		) .
		Html::element(
			'input',
			[
				'type' => 'hidden',
				'name' => 'clientUrl',
				'value' => $targetUrl->getUrl(),
			]
		) .
		Html::element(
			'input',
			[
				'type' => 'hidden',
				'name' => 'importDetailsHash',
				'value' => $importDetails->getHash(),
			]
		) .
		Html::element(
			'input',
			[
				'type' => 'hidden',
				'name' => 'token',
				'value' => $this->specialPage->getUser()->getEditToken()
			]
		) .
		new ButtonInputWidget(
			[
				'classes' => [ 'mw-importfile-import-submit' ],
				'label' => ( new Message( 'fileimporter-import' ) )->plain(),
				'type' => 'submit',
				'flags' => [ 'primary', 'progressive' ],
			]
		) .
		new ButtonInputWidget(
			[
				'classes' => [ 'mw-importfile-import-cancel' ],
				'label' => ( new Message( 'fileimporter-cancel' ) )->plain(),
				'type' => 'reset',
				'flags' => [ 'progressive' ],
			]
		) .
		Html::closeElement( 'form' ) .
		Html::closeElement(
			'div'
		);
	}

}
