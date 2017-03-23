<?php

namespace FileImporter\Html;

use FileImporter\Generic\Data\ImportDetails;
use Html;
use Linker;
use Message;
use OOUI\ButtonInputWidget;
use OOUI\TextInputWidget;
use SpecialPage;
use Title;

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
	 * @var Title
	 */
	private $title;

	/**
	 * @param SpecialPage $specialPage
	 * @param ImportDetails $importDetails
	 * @param Title $title
	 */
	public function __construct(
		SpecialPage $specialPage,
		ImportDetails $importDetails,
		Title $title
	) {
		$this->specialPage = $specialPage;
		$this->importDetails = $importDetails;
		$this->title = $title;
	}

	/**
	 * @return string
	 */
	public function getHtml() {
		$importDetails = $this->importDetails;
		$targetUrl = $importDetails->getTargetUrl();

		$importIdentityFormSnippet = ( new ImportIdentityFormSnippet( [
			'clientUrl' => $targetUrl->getUrl(),
			'intendedTitle' => $this->title->getText(),
			'importDetailsHash' => $importDetails->getHash(),
		] ) )->getHtml();

		return
		Html::element(
			'p',
			[],
			( new Message( 'fileimporter-previewnote' ) )->parse()
		) .
		Html::rawElement(
			'h2',
			[],
			$this->title->getPrefixedText() .
			Html::openElement(
				'form',
				[
					'class' => 'mw-importfile-rightAlign',
					'action' => $this->specialPage->getPageTitle()->getLocalURL(),
					'method' => 'GET',
				]
			) .
			Html::element(
				'input',
				[
					'type' => 'hidden',
					'name' => 'action',
					'value' => 'edittitle',
				]
			) .
			$importIdentityFormSnippet .
			new ButtonInputWidget(
				[
					'classes' => [ 'mw-importfile-edittitle' ],
					'label' => ( new Message( 'fileimporter-edittitle' ) )->plain(),
					'type' => 'submit',
					'flags' => [ 'progressive' ],
				]
			) .
			Html::closeElement( 'form' )
		) .
		Linker::makeExternalImage(
			$importDetails->getImageDisplayUrl(),
			$this->title->getPrefixedText()
		) .
		Html::rawElement(
			'h2',
			[],
			( new Message( 'fileimporter-heading-fileinfo' ) )->plain() .
			Html::openElement(
				'form',
				[
					'class' => 'mw-importfile-rightAlign',
					'action' => $this->specialPage->getPageTitle()->getLocalURL(),
					'method' => 'GET',
				]
			) .
			Html::element(
				'input',
				[
					'type' => 'hidden',
					'name' => 'action',
					'value' => 'editinfo',
				]
			) .
			$importIdentityFormSnippet .
			new ButtonInputWidget(
				[
					'classes' => [ 'mw-importfile-editinfo' ],
					'label' => ( new Message( 'fileimporter-editinfo' ) )->plain(),
					'type' => 'submit',
					'flags' => [ 'progressive' ],
				]
			) .
			Html::closeElement( 'form' )
		) .
		Html::rawElement(
			'div',
			[ 'class' => 'mw-importfile-parsedContent' ],
			( new TextRevisionSnippet( $importDetails->getTextRevisions()->getLatest() ) )->getHtml()
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
				[ count( $importDetails->getTextRevisions()->toArray() ) ]
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
		$importIdentityFormSnippet .
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
