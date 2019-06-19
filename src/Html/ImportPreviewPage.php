<?php

namespace FileImporter\Html;

use FileImporter\Data\ImportPlan;
use Html;
use Linker;
use OOUI\ButtonInputWidget;
use OOUI\ButtonWidget;
use OOUI\IconWidget;
use OOUI\TextInputWidget;
use MediaWiki\MediaWikiServices;

/**
 * Page displaying the preview of the import before it has happened.
 *
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class ImportPreviewPage extends SpecialPageHtmlFragment {

	const ACTION_EDIT_TITLE = 'edittitle';
	const ACTION_EDIT_INFO = 'editinfo';
	const ACTION_SUBMIT = 'submit';
	const ACTION_VIEW_DIFF = 'viewdiff';

	/**
	 * @param ImportPlan $importPlan
	 *
	 * @return string
	 */
	public function getHtml( ImportPlan $importPlan ) {
		$text = $importPlan->getFileInfoText();
		$title = $importPlan->getTitle();

		$details = $importPlan->getDetails();
		$textRevisionsCount = count( $details->getTextRevisions()->toArray() );
		$fileRevisionsCount = count( $details->getFileRevisions()->toArray() );
		$importIdentityFormSnippet = $this->buildImportIdentityFormSnippet( $importPlan );
		$categoriesSnippet = $this->buildCategoriesSnippet( $importPlan );

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
				$title->getText()
			) .
			$this->buildActionFormStart(
				self::ACTION_EDIT_TITLE,
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
				self::ACTION_EDIT_INFO,
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
				$text
			)
		) .
		$categoriesSnippet .
		Html::rawElement(
			'div',
			[],
			new IconWidget( [ 'icon' => 'info' ] ) .
			$this->msg( 'fileimporter-category-encouragement' )->parse()
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
		$this->buildActionFormStart( self::ACTION_SUBMIT ) .
		$importIdentityFormSnippet .
		( $this->wasEdited( $importPlan ) ? $this->buildEditSummaryHtml(
			$importPlan->getNumberOfTemplateReplacements() ) : '' ) .
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
				'value' => self::ACTION_VIEW_DIFF,
				'type' => 'submit',
			]
		);
	}

	private function buildEditSummaryHtml( $replacements ) {
		$summary = $replacements > 0
			? $this->msg( 'fileimporter-auto-replacements-summary', $replacements )
				->inContentLanguage()
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

	/**
	 * @param ImportPlan $importPlan
	 * @return string
	 */
	private function buildImportIdentityFormSnippet( ImportPlan $importPlan ) {
		return ( new ImportIdentityFormSnippet( [
			'clientUrl' => $importPlan->getRequest()->getUrl(),
			'intendedFileName' => $importPlan->getFileName(),
			'intendedWikitext' => $importPlan->getFileInfoText(),
			'actionStats' => json_encode( $importPlan->getActionStats() ),
			'importDetailsHash' => $importPlan->getDetails()->getOriginalHash(),
		] ) )->getHtml();
	}

	/**
	 * @param ImportPlan $importPlan
	 * @return string HTML snippet for a box showing the categories, or empty string if there are
	 * no categories.
	 */
	private function buildCategoriesSnippet( ImportPlan $importPlan ) {
		$categoryExtractor = MediaWikiServices::getInstance()
			->getService( 'FileImporterCategoryExtractor' );
		list( $visibleCategories, $hiddenCategories ) = $categoryExtractor->getCategoriesGrouped(
			$importPlan->getFileInfoText(),
			$importPlan->getTitle()
		);
		return ( new CategoriesSnippet(
			$visibleCategories,
			$hiddenCategories
		) )->getHtml();
	}

	/**
	 * @param ImportPlan $importPlan
	 * @return bool
	 */
	private function wasEdited( ImportPlan $importPlan ) {
		return $importPlan->wasFileInfoTextChanged() ||
			$importPlan->getNumberOfTemplateReplacements() > 0;
	}

}
