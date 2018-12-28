<?php

namespace FileImporter\Html;

use FileImporter\Data\ImportPlan;
use Html;
use OOUI\ButtonInputWidget;
use SpecialPage;

/**
 * Form allowing the user to change the file info text.
 *
 * @license GPL-2.0-or-later
 * @author Christoph Jauera <christoph.jauera@wikimedia.de>
 */
class ChangeFileInfoForm {

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
		$wikiTextValue = $this->importPlan->getRequest()->getIntendedText();
		if ( $wikiTextValue === null ) {
			$wikiTextValue = $this->importPlan->getFileInfoText();
		}

		return Html::openElement(
			'form',
			[
				'action' => $this->specialPage->getPageTitle()->getLocalURL(),
				'method' => 'POST',
			]
		) .
		( new WikiTextEditor( $this->specialPage ) )->getHtml( $wikiTextValue ) .
		( new ImportIdentityFormSnippet( [
			'clientUrl' => $this->importPlan->getRequest()->getUrl(),
			'intendedFileName' => $this->importPlan->getFileName(),
			'importDetailsHash' => $this->importPlan->getRequest()->getImportDetailsHash(),
		] ) )->getHtml() .
		new ButtonInputWidget(
			[
				'label' => $this->specialPage->msg( 'fileimporter-submit' )->plain(),
				'type' => 'submit',
				'flags' => [ 'primary', 'progressive' ],
				'tabIndex' => 2,
			]
		) .
		Html::closeElement( 'form' );
	}

}
