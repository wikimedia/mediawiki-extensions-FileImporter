<?php

namespace FileImporter\Html;

use FileImporter\Data\ImportPlan;
use Html;
use OOUI\FieldLayout;
use OOUI\FieldsetLayout;
use OOUI\ButtonInputWidget;
use OOUI\TextInputWidget;
use SpecialPage;

/**
 * Form allowing the user to select a new file name.
 *
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class ChangeFileNameForm {

	/**
	 * @var SpecialPage
	 */
	private $specialPage;

	/**
	 * @var ImportPlan
	 */
	private $importPlan;

	public function __construct( SpecialPage $specialPage, ImportPlan $importPlan ) {
		$this->specialPage = $specialPage;
		$this->importPlan = $importPlan;
	}

	/**
	 * @return string
	 */
	public function getHtml() {
		$filenameValue = $this->importPlan->getFileName();

		return Html::openElement(
			'form',
			[
				'action' => $this->specialPage->getPageTitle()->getLocalURL(),
				'method' => 'POST',
			]
		) .
		( new FieldsetLayout( [
			'items' => [ new FieldLayout(
				new TextInputWidget(
					[
						'name' => 'intendedFileName',
						'value' => $filenameValue,
						'classes' => [ 'mw-importfile-import-newtitle' ],
						'placeholder' => $this->specialPage->msg( 'fileimporter-newfilename-placeholder' )->plain(),
						'suggestions' => false,
						'autofocus' => true,
						'required' => true,
					]
				),
				[
					'align' => 'top',
					'label' => $this->specialPage->msg( 'fileimporter-newfilename' )->plain(),
				]
			) ]
		] ) ) .
		// TODO allow changing the case of the file extension
		Html::element(
			'p',
			[],
			$this->specialPage->msg( 'fileimporter-extensionlabel' )->plain() .
			' ' .
			$this->importPlan->getFileExtension()
		) .
		( new ImportIdentityFormSnippet( [
			'clientUrl' => $this->importPlan->getRequest()->getUrl(),
			'intendedWikiText' => $this->importPlan->getFileInfoText(),
			'importDetailsHash' => $this->importPlan->getRequest()->getImportDetailsHash(),
		] ) )->getHtml() .
		new ButtonInputWidget(
			[
				'label' => $this->specialPage->msg( 'fileimporter-submit' )->plain(),
				'type' => 'submit',
				'flags' => [ 'primary', 'progressive' ],
			]
		) .
		Html::closeElement( 'form' );
	}

}
