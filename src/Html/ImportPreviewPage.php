<?php

namespace FileImporter\Html;

use FileImporter\Data\ImportPlan;
use FileImporter\SpecialImportFile;
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
			$this->buildActionFormStart(
				SpecialImportFile::ACTION_EDIT_TITLE,
				'mw-importfile-rightAlign'
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
			) .
			$this->buildActionFormStart(
				SpecialImportFile::ACTION_EDIT_INFO,
				'mw-importfile-rightAlign'
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
		$this->buildActionFormStart( SpecialImportFile::ACTION_SUBMIT ) .
		$importIdentityFormSnippet .
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
				'label' => $this->specialPage->msg( 'fileimporter-import' )->plain(),
				'type' => 'submit',
				'flags' => [ 'primary', 'progressive' ],
			]
		) .
		( $this->wasEdited() ? $this->buildShowChangesButtonHtml() : '' ) .
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

	private function buildActionFormStart( $action, $class = '' ) {
		return Html::openElement(
			'form',
			[
				'class' => $class,
				'action' => $this->specialPage->getPageTitle()->getLocalURL(),
				'method' => 'POST',
			]
		) .
		Html::element(
			'input',
			[
				'type' => 'hidden',
				'name' => 'action',
				'value' => $action,
			]
		);
	}

	private function buildShowChangesButtonHtml() {
		return new ButtonInputWidget(
			[
				'classes' => [ 'mw-importfile-import-diff' ],
				'label' => $this->specialPage->msg( 'fileimporter-viewdiff' )->plain(),
				'name' => 'action',
				'value' => SpecialImportFile::ACTION_VIEW_DIFF,
				'type' => 'submit',
				'flags' => [ 'progressive' ],
			]
		);
	}

	private function buildEditSummaryHtml() {
		$replacements = $this->importPlan->getDetails()->getNumberOfTemplatesReplaced();
		$summary = $replacements > 0
			? $this->specialPage->msg( 'fileimporter-auto-replacements-summary', $replacements )
				->text()
			: null;

		return Html::element(
			'p',
			[],
			$this->specialPage->msg( 'fileimporter-editsummary' )->plain()
		) .
		new TextInputWidget(
			[
				'name' => 'intendedRevisionSummary',
				'classes' => [ 'mw-importfile-import-summary' ],
				'value' => $summary,
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
		return $this->importPlan->wasFileInfoTextChanged() ||
			$this->importPlan->getDetails()->getNumberOfTemplatesReplaced() > 0;
	}

}
