<?php

namespace FileImporter\Html;

use FileImporter\Data\ImportPlan;
use Html;
use OOUI\ButtonInputWidget;

/**
 * Form allowing the user to change the file info text.
 *
 * @license GPL-2.0-or-later
 * @author Christoph Jauera <christoph.jauera@wikimedia.de>
 */
class ChangeFileInfoForm extends SpecialPageHtmlFragment {

	/**
	 * @param ImportPlan $importPlan
	 *
	 * @return string
	 */
	public function getHtml( ImportPlan $importPlan ) {
		// Try showing the user provided value first if present
		$wikiTextValue = $importPlan->getRequest()->getIntendedText();
		if ( $wikiTextValue === null ) {
			$wikiTextValue = $importPlan->getFileInfoText();
		}

		return Html::openElement(
			'form',
			[
				'action' => $this->getPageTitle()->getLocalURL(),
				'method' => 'POST',
			]
		) .
		( new WikiTextEditor( $this ) )->getHtml( $wikiTextValue ) .
		( new ImportIdentityFormSnippet( [
			'clientUrl' => $importPlan->getRequest()->getUrl(),
			'intendedFileName' => $importPlan->getFileName(),
			'importDetailsHash' => $importPlan->getRequest()->getImportDetailsHash(),
		] ) )->getHtml() .
		new ButtonInputWidget(
			[
				'label' => $this->msg( 'fileimporter-submit-fileinfo' )->plain(),
				'type' => 'submit',
				'flags' => [ 'primary', 'progressive' ],
				'tabIndex' => 2,
			]
		) .
		Html::closeElement( 'form' );
	}

}
