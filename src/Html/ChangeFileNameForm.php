<?php

namespace FileImporter\Html;

use FileImporter\Data\ImportPlan;
use Html;
use OOUI\ButtonInputWidget;
use OOUI\FieldLayout;
use OOUI\FieldsetLayout;
use OOUI\TextInputWidget;

/**
 * Form allowing the user to select a new file name.
 *
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class ChangeFileNameForm extends SpecialPageHtmlFragment {

	/**
	 * @param ImportPlan $importPlan
	 *
	 * @return string
	 */
	public function getHtml( ImportPlan $importPlan ) {
		$filenameValue = $importPlan->getRequest()->getIntendedName() ?? $importPlan->getFileName();

		return Html::openElement(
			'form',
			[
				'action' => $this->getPageTitle()->getLocalURL(),
				'method' => 'POST',
			]
		) .
		( new FieldsetLayout( [
			'items' => [ new FieldLayout(
				new TextInputWidget(
					[
						'name' => 'intendedFileName',
						'value' => $filenameValue,
						'suggestions' => false,
						'autofocus' => true,
						'required' => true,
					]
				),
				[
					'align' => 'top',
					'label' => $this->msg( 'fileimporter-newfilename' )->plain(),
				]
			) ]
		] ) ) .
		// TODO allow changing the case of the file extension
		Html::element(
			'p',
			[],
			$this->msg( 'fileimporter-extensionlabel' )->plain() .
				' .' . $importPlan->getFileExtension()
		) .
		( new ImportIdentityFormSnippet( [
			'clientUrl' => $importPlan->getRequest()->getUrl(),
			'intendedWikitext' => $importPlan->getFileInfoText(),
			'actionStats' => json_encode( $importPlan->getActionStats() ),
			'validationWarnings' => json_encode( $importPlan->getValidationWarnings() ),
			'importDetailsHash' => $importPlan->getRequest()->getImportDetailsHash(),
			'intendedRevisionSummary' => $importPlan->getRequest()->getIntendedSummary(),
			'automateSourceWikiCleanup' => $importPlan->getAutomateSourceWikiCleanUp(),
			'automateSourceWikiDelete' => $importPlan->getAutomateSourceWikiDelete(),
		] ) )->getHtml() .
		new ButtonInputWidget(
			[
				'classes' => [ 'mw-importfile-backButton' ],
				'label' => $this->msg( 'fileimporter-submit-title' )->plain(),
				'type' => 'submit',
				'flags' => [ 'primary', 'progressive' ],
			]
		) .
		Html::closeElement( 'form' );
	}

}
