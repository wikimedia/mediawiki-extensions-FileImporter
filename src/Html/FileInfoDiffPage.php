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
		$newText = $importPlan->getRequest()->getIntendedText();
		if ( $newText === null ) {
			$newText = $importPlan->getFileInfoText();
		}

		return Html::openElement(
				'form',
				[
					'action' => $this->getPageTitle()->getLocalURL(),
					'method' => 'POST',
				]
			) .
			Html::rawElement(
				'div',
				[ 'class' => 'mw-importfile-diff-view' ],
				$this->buildDiff( $importPlan->getTitle(), $importPlan->getInitialFileInfoText(), $newText )
			) .
			( new ImportIdentityFormSnippet( [
				'clientUrl' => $importPlan->getRequest()->getUrl(),
				'intendedFileName' => $importPlan->getFileName(),
				'intendedWikiText' => $importPlan->getFileInfoText(),
				'importDetailsHash' => $importPlan->getRequest()->getImportDetailsHash(),
			] ) )->getHtml() .
			new ButtonInputWidget(
				[
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
			$this->msg( 'currentrev' ),
			$this->msg( 'yourtext' )
		);
	}

}
