<?php

namespace FileImporter\Html;

use FileImporter\Data\ImportPlan;
use Html;
use Linker;
use OOUI\ButtonInputWidget;
use OOUI\ButtonWidget;
use OOUI\TextInputWidget;
use SpecialPage;

/**
 * Page displaying the preview of the import before it has happened.
 *
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class ImportPreviewPage {

	/**
	 * @var SpecialPage
	 */
	private $specialPage;

	/**
	 * @var ImportPlan
	 */
	private $importPlan;

	/**
	 * @param SpecialPage $specialPage
	 * @param ImportPlan $importPlan
	 */
	public function __construct(
		SpecialPage $specialPage,
		ImportPlan $importPlan
	) {
		$this->specialPage = $specialPage;
		$this->importPlan = $importPlan;
	}

	/**
	 * @return string
	 */
	public function getHtml() {
		$details = $this->importPlan->getDetails();
		$title = $this->importPlan->getTitle();
		$textRevisionsCount = count( $details->getTextRevisions()->toArray() );
		$fileRevisionsCount = count( $details->getFileRevisions()->toArray() );
		$importIdentityFormSnippet = $this->buildImportIdentityFormSnippet();

		return Html::element(
			'p',
			[],
			$this->specialPage->msg( 'fileimporter-previewnote' )->parse()
		) .
		Html::rawElement(
			'div',
			[ 'class' => 'mw-importfile-header' ],
			Html::element(
				'h2',
				[
					'class' => 'mw-importfile-header-title'
				],
				$this->importPlan->getTitle()->getText()
			) .
			Html::openElement(
				'form',
				[
					'class' => 'mw-importfile-rightAlign',
					'action' => $this->specialPage->getPageTitle()->getLocalURL(),
					'method' => 'POST',
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
					'classes' => [ 'mw-importfile-edit-button' ],
					'label' => $this->specialPage->msg( 'fileimporter-edittitle' )->plain(),
					'type' => 'submit',
					'flags' => [ 'progressive' ],
				]
			) .
			Html::closeElement( 'form' )
		) .
		Linker::makeExternalImage(
			$details->getImageDisplayUrl(),
			$title->getPrefixedText()
		) .
		Html::rawElement(
			'div',
			[ 'class' => 'mw-importfile-header' ],
			Html::element(
				'h2',
				[
					'class' => 'mw-importfile-header-title'
				],
				$this->specialPage->msg( 'fileimporter-heading-fileinfo' )->plain()
			).Html::openElement(
				'form',
				[
					'class' => 'mw-importfile-rightAlign',
					'action' => $this->specialPage->getPageTitle()->getLocalURL(),
					'method' => 'POST',
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
					'classes' => [ 'mw-importfile-edit-button' ],
					'label' => $this->specialPage->msg( 'fileimporter-editinfo' )->plain(),
					'type' => 'submit',
					'flags' => [ 'progressive' ],
				]
			) .
			Html::closeElement( 'form' )
		) .
		Html::rawElement(
			'div',
			[ 'class' => 'mw-importfile-parsedContent' ],
			( new TextRevisionSnippet(
				$details->getTextRevisions()->getLatest(),
				$this->importPlan->getFileInfoText()
			) )->getHtml()
		) .
		Html::element(
			'h2',
			[],
			$this->specialPage->msg( 'fileimporter-heading-filehistory' )->plain()
		) .
		Html::element(
			'p',
			[],
			$this->specialPage->msg(
				'fileimporter-filerevisions',
				[
					$fileRevisionsCount,
					$fileRevisionsCount,
				]
			)->parse()
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
		( $this->wasEdited() ? $this->buildEditSummaryHtml() : '' ) .
		Html::element(
			'p',
			[],
			$this->specialPage->msg(
				'fileimporter-textrevisions',
				[
					$textRevisionsCount,
					$textRevisionsCount,
				]
			)->parse()
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
		Html::element(
			'input',
			[
				'type' => 'hidden',
				'name' => 'action',
				'value' => 'submit',
			]
		) .
		new ButtonInputWidget(
			[
				'classes' => [ 'mw-importfile-import-submit' ],
				'label' => $this->specialPage->msg( 'fileimporter-import' )->plain(),
				'type' => 'submit',
				'flags' => [ 'primary', 'progressive' ],
			]
		) .
		new ButtonWidget(
			[
				'classes' => [ 'mw-importfile-import-cancel' ],
				'label' => $this->specialPage->msg( 'fileimporter-cancel' )->plain(),
				'flags' => [ 'progressive' ],
				'href' => $this->importPlan->getRequest()->getUrl()->getUrl()
			]
		) .
		Html::element(
			'span',
			[],
			$this->specialPage->msg( 'fileimporter-import-wait' )->plain()
		) .
		Html::closeElement( 'form' ) .
		Html::closeElement(
			'div'
		);
	}

	private function buildEditSummaryHtml() {
		return Html::element(
			'p',
			[],
			$this->specialPage->msg( 'fileimporter-editsummary' )->plain()
		) .
		new TextInputWidget(
			[
				'name' => 'intendedRevisionSummary',
				'classes' => [ 'mw-importfile-import-summary' ],
			]
		);
	}

	private function buildImportIdentityFormSnippet() {
		return ( new ImportIdentityFormSnippet( [
			'clientUrl' => $this->importPlan->getRequest()->getUrl(),
			'intendedFileName' => $this->importPlan->getFileName(),
			'intendedWikiText' => $this->importPlan->getFileInfoText(),
			'importDetailsHash' => $this->importPlan->getDetails()->getOriginalHash(),
		] ) )->getHtml();
	}

	private function wasEdited() {
		return $this->importPlan->wasFileInfoTextChanged();
	}

}
