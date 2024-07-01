<?php

namespace FileImporter\Html;

use FileImporter\Data\ImportPlan;
use MediaWiki\Html\Html;
use MediaWiki\Title\MalformedTitleException;
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

	public function getHtml( ImportPlan $importPlan ): string {
		try {
			$filenameValue = $importPlan->getFileName();
		} catch ( MalformedTitleException $ex ) {
			$filenameValue = $importPlan->getRequest()->getIntendedName();
		}

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
