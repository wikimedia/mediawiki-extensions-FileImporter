<?php

namespace FileImporter\Html;

use FileImporter\Data\ImportPlan;
use Html;
use Linker;
use Message;
use OOUI\ButtonInputWidget;
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
		$request = $this->importPlan->getRequest();
		$title = $this->importPlan->getTitle();
		$wasEdited = $this->importPlan->wasFileNameChanged() ||
			$this->importPlan->wasFileInfoTextChanged();
		$textRevisionsCount = count( $details->getTextRevisions()->toArray() );
		$fileRevisionsCount = count( $details->getFileRevisions()->toArray() );

		$importIdentityFormSnippet = ( new ImportIdentityFormSnippet( [
			'clientUrl' => $request->getUrl(),
			'intendedFileName' => $this->importPlan->getFileName(),
			'intendedWikiText' => $this->importPlan->getFileInfoText(),
			'importDetailsHash' => $details->getOriginalHash(),
		] ) )->getHtml();

		return Html::element(
			'p',
			[],
			( new Message( 'fileimporter-previewnote' ) )->parse()
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
					'label' => ( new Message( 'fileimporter-edittitle' ) )->plain(),
					'type' => 'submit',
					'flags' => [ 'progressive' ],
				]
			) .
			Html::closeElement( 'form' )
		).
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
				( new Message( 'fileimporter-heading-fileinfo' ) )->plain()
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
			( new TextRevisionSnippet(
				$details->getTextRevisions()->getLatest(),
				$this->importPlan->getFileInfoText()
			) )->getHtml()
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
				'fileimporter-filerevisions',
				[
					$fileRevisionsCount,
					$fileRevisionsCount,
				]
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
				'name' => 'intendedRevisionSummary',
				'classes' => [ 'mw-importfile-import-summary' ],
				'placeholder' => ( new Message( 'fileimporter-editsummary-placeholder' ) )->plain(),
				'disabled' => !$wasEdited,
			]
		) .
			Html::element(
				'p',
				[],
				( new Message(
					'fileimporter-textrevisions',
					[
						$textRevisionsCount,
						$textRevisionsCount,
					]
				) )->parse()
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
		Html::element(
			'span',
			[],
			( new Message( 'fileimporter-import-wait' ) )->plain()
		) .
		Html::closeElement( 'form' ) .
		Html::closeElement(
			'div'
		);
	}

}
