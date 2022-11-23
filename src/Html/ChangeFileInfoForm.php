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
		$wikitext = $importPlan->getRequest()->getIntendedText() ?? $importPlan->getFileInfoText();

		return Html::openElement(
			'form',
			[
				'action' => $this->getPageTitle()->getLocalURL(),
				'method' => 'POST',
			]
		) .
		( new WikitextEditor( $this ) )->getHtml( $importPlan->getTitle(), $wikitext ) .
		( new ImportIdentityFormSnippet( [
			'clientUrl' => $importPlan->getRequest()->getUrl(),
			'intendedFileName' => $importPlan->getFileName(),
			'intendedRevisionSummary' => $importPlan->getRequest()->getIntendedSummary(),
			'actionStats' => json_encode( $importPlan->getActionStats() ),
			'validationWarnings' => json_encode( $importPlan->getValidationWarnings() ),
			'importDetailsHash' => $importPlan->getRequest()->getImportDetailsHash(),
			'automateSourceWikiCleanup' => $importPlan->getAutomateSourceWikiCleanUp(),
			'automateSourceWikiDelete' => $importPlan->getAutomateSourceWikiDelete(),
		] ) )->getHtml() .
		new ButtonInputWidget(
			[
				'classes' => [ 'mw-importfile-backButton' ],
				'label' => $this->msg( 'fileimporter-submit-fileinfo' )->plain(),
				'type' => 'submit',
				'flags' => [ 'primary', 'progressive' ],
				'tabIndex' => 2,
			]
		) .
		Html::closeElement( 'form' );
	}

}
