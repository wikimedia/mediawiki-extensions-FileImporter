<?php

namespace FileImporter\Html;

use ContentHandler;
use FileImporter\Data\ImportPlan;
use Html;
use OOUI\ButtonInputWidget;
use Title;

/**
 * Page to display a diff from changed file info text.
 *
 * @license GPL-2.0-or-later
 * @author Christoph Jauera <christoph.jauera@wikimedia.de>
 */
class FileInfoDiffPage extends SpecialPageHtmlFragment {

	/**
	 * @param ImportPlan $importPlan
	 *
	 * @return string
	 */
	public function getHtml( ImportPlan $importPlan ) {
		$newText = $importPlan->getRequest()->getIntendedText() ?? $importPlan->getFileInfoText();

		return Html::openElement(
				'form',
				[
					'action' => $this->getPageTitle()->getLocalURL(),
					'method' => 'POST',
				]
			) .
			Html::rawElement(
				'div',
				[],
				$this->buildDiff( $importPlan->getTitle(), $importPlan->getInitialFileInfoText(), $newText )
			) .
			( new ImportIdentityFormSnippet( [
				'clientUrl' => $importPlan->getRequest()->getUrl(),
				'intendedFileName' => $importPlan->getFileName(),
				'intendedRevisionSummary' => $importPlan->getRequest()->getIntendedSummary(),
				'intendedWikitext' => $importPlan->getFileInfoText(),
				'actionStats' => json_encode( $importPlan->getActionStats() ),
				'validationWarnings' => json_encode( $importPlan->getValidationWarnings() ),
				'importDetailsHash' => $importPlan->getRequest()->getImportDetailsHash(),
				'automateSourceWikiCleanup' => $importPlan->getAutomateSourceWikiCleanUp(),
				'automateSourceWikiDelete' => $importPlan->getAutomateSourceWikiDelete(),
			] ) )->getHtml() .
			new ButtonInputWidget(
				[
					'classes' => [ 'mw-importfile-backButton' ],
					'label' => $this->msg( 'fileimporter-to-preview' )->plain(),
					'type' => 'submit',
					'flags' => [ 'primary', 'progressive' ],
				]
			) .
			Html::closeElement( 'form' );
	}

	/**
	 * @param Title $title
	 * @param string $originalText
	 * @param string $newText
	 *
	 * @return string HTML
	 */
	private function buildDiff( Title $title, $originalText, $newText ) {
		$originalContent = ContentHandler::makeContent(
			$originalText,
			$title,
			CONTENT_MODEL_WIKITEXT,
			CONTENT_FORMAT_WIKITEXT
		);
		$newContent = ContentHandler::makeContent(
			$newText,
			$title,
			CONTENT_MODEL_WIKITEXT,
			CONTENT_FORMAT_WIKITEXT
		);

		$contenHandler = ContentHandler::getForModelID( CONTENT_MODEL_WIKITEXT );
		$diffEngine = $contenHandler->createDifferenceEngine( $this->getContext() );
		$diffEngine->setContent( $originalContent, $newContent );

		$diffEngine->showDiffStyle();
		return $diffEngine->getDiff(
			$this->msg( 'currentrev' )->parse(),
			$this->msg( 'yourtext' )->parse()
		);
	}

}
