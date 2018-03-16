<?php

namespace FileImporter\Html;

use FileImporter\Data\ImportPlan;
use Html;
use Message;
use OOUI\FieldLayout;
use OOUI\FieldsetLayout;
use OOUI\ButtonInputWidget;
use OOUI\TextInputWidget;
use SpecialPage;

/**
 * Form allowing the user to select a new file name.
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
		// Try showing the user provided value first if present
		$filenameValue = $this->importPlan->getRequest()->getIntendedName();
		if ( $filenameValue === null ) {
			$filenameValue = $this->importPlan->getTitleText();
		}

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
						'placeholder' => ( new Message( 'fileimporter-newfilename-placeholder' ) )->plain(),
						'suggestions' => false,
						'autofocus' => true,
						'required' => true,
					]
				),
				[
					'align' => 'top',
					'label' => ( new Message( 'fileimporter-newfilename' ) )->plain(),
				]
			) ]
		] ) ) .
		// TODO allow changing the case of the file extension
		Html::element(
			'p',
			[],
			( new Message( 'fileimporter-extensionlabel' ) )->plain() .
			' ' .
			$this->importPlan->getFileExtension()
		) .
		( new ImportIdentityFormSnippet( [
			'clientUrl' => $this->importPlan->getRequest()->getUrl(),
			'intendedWikiText' => $this->importPlan->getFileInfoText(),
			'importDetailsHash' => $this->specialPage->getRequest()->getVal( 'importDetailsHash' ),
		] ) )->getHtml() .
		new ButtonInputWidget(
			[
				'label' => ( new Message( 'fileimporter-submit' ) )->plain(),
				'type' => 'submit',
				'flags' => [ 'primary', 'progressive' ],
			]
		) .
		Html::closeElement( 'form' );
	}

}
