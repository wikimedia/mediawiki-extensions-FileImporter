<?php

namespace FileImporter\Html;

use FileImporter\Data\ImportPlan;
use FileImporter\SpecialImportFile;
use Html;
use Linker;
use OOUI\ButtonInputWidget;
use OOUI\ButtonWidget;
use OOUI\TextInputWidget;

/**
 * Page displaying the preview of the import before it has happened.
 *
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class ImportPreviewPage extends SpecialPageHtmlFragment {

	/**
	 * @param ImportPlan $importPlan
	 *
	 * @return string
	 */
	public function getHtml( ImportPlan $importPlan ) {
		$details = $importPlan->getDetails();
		$title = $importPlan->getTitle();
		$textRevisionsCount = count( $details->getTextRevisions()->toArray() );
		$fileRevisionsCount = count( $details->getFileRevisions()->toArray() );
		$importIdentityFormSnippet = $this->buildImportIdentityFormSnippet( $importPlan );

		return Html::rawElement(
			'p',
			[],
			$this->msg( 'fileimporter-previewnote' )->parse()
		) .
		Html::rawElement(
			'div',
			[ 'class' => 'mw-importfile-header' ],
			Html::element(
				'h2',
				[
					'class' => 'mw-importfile-header-title'
				],
				$importPlan->getTitle()->getText()
			) .
			$this->buildActionFormStart(
				SpecialImportFile::ACTION_EDIT_TITLE,
				'mw-importfile-rightAlign'
			) .
			$importIdentityFormSnippet .
			new ButtonInputWidget(
				[
					'classes' => [ 'mw-importfile-edit-button' ],
					'label' => $this->msg( 'fileimporter-edittitle' )->plain(),
					'type' => 'submit',
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
				$this->msg( 'fileimporter-heading-fileinfo' )->plain()
			) .
			$this->buildActionFormStart(
				SpecialImportFile::ACTION_EDIT_INFO,
				'mw-importfile-rightAlign'
			) .
			$importIdentityFormSnippet .
			new ButtonInputWidget(
				[
					'classes' => [ 'mw-importfile-edit-button' ],
					'label' => $this->msg( 'fileimporter-editinfo' )->plain(),
					'type' => 'submit',
				]
			) .
			Html::closeElement( 'form' )
		) .
		Html::rawElement(
			'div',
			[ 'class' => 'mw-importfile-parsedContent' ],
			( new TextRevisionSnippet( $this ) )->getHtml(
				$details->getTextRevisions()->getLatest(),
				$importPlan->getFileInfoText()
			)
		) .
		Html::element(
			'h2',
			[],
			$this->msg( 'fileimporter-heading-filehistory' )->plain()
		) .
		Html::rawElement(
			'p',
			[],
			$this->msg(
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
		( $this->wasEdited( $importPlan ) ? $this->buildEditSummaryHtml(
			$importPlan->getDetails()->getNumberOfTemplatesReplaced() ) : '' ) .
		Html::rawElement(
			'p',
			[],
			$this->msg(
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
				'value' => $this->getUser()->getEditToken()
			]
		) .
		new ButtonInputWidget(
			[
				'classes' => [ 'mw-importfile-import-submit' ],
				'label' => $this->msg( 'fileimporter-import' )->plain(),
				'type' => 'submit',
				'flags' => [ 'primary', 'progressive' ],
			]
		) .
		( $this->wasEdited( $importPlan ) ? $this->buildShowChangesButtonHtml() : '' ) .
		new ButtonWidget(
			[
				'classes' => [ 'mw-importfile-import-cancel' ],
				'label' => $this->msg( 'fileimporter-cancel' )->plain(),
				'href' => $importPlan->getRequest()->getUrl()->getUrl()
			]
		) .
		Html::element(
			'span',
			[],
			$this->msg( 'fileimporter-import-wait' )->plain()
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
				'action' => $this->getPageTitle()->getLocalURL(),
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
				'label' => $this->msg( 'fileimporter-viewdiff' )->plain(),
				'name' => 'action',
				'value' => SpecialImportFile::ACTION_VIEW_DIFF,
				'type' => 'submit',
			]
		);
	}

	private function buildEditSummaryHtml( $replacements ) {
		$summary = $replacements > 0
			? $this->msg( 'fileimporter-auto-replacements-summary', $replacements )
				->text()
			: null;

		return Html::element(
			'p',
			[],
			$this->msg( 'fileimporter-editsummary' )->plain()
		) .
		new TextInputWidget(
			[
				'name' => 'intendedRevisionSummary',
				'classes' => [ 'mw-importfile-import-summary' ],
				'value' => $summary,
			]
		);
	}

	private function buildImportIdentityFormSnippet( ImportPlan $importPlan ) {
		return ( new ImportIdentityFormSnippet( [
			'clientUrl' => $importPlan->getRequest()->getUrl(),
			'intendedFileName' => $importPlan->getFileName(),
			'intendedWikiText' => $importPlan->getFileInfoText(),
			'importDetailsHash' => $importPlan->getDetails()->getOriginalHash(),
		] ) )->getHtml();
	}

	private function wasEdited( ImportPlan $importPlan ) {
		return $importPlan->wasFileInfoTextChanged() ||
			$importPlan->getDetails()->getNumberOfTemplatesReplaced() > 0;
	}

}
