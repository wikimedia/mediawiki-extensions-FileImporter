<?php

namespace FileImporter\Html;

use ContentHandler;
use FileImporter\Data\ImportPlan;
use Html;
use OOUI\ButtonInputWidget;
use SpecialPage;

/**
 * Page to display a diff from changed file info text.
 *
 * @license GPL-2.0-or-later
 * @author Christoph Jauera <christoph.jauera@wikimedia.de>
 */
class FileInfoDiffPage {

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
		$newText = $this->importPlan->getRequest()->getIntendedText();
		if ( $newText === null ) {
			$newText = $this->importPlan->getFileInfoText();
		}

		return Html::openElement(
				'form',
				[
					'action' => $this->specialPage->getPageTitle()->getLocalURL(),
					'method' => 'POST',
				]
			) .
			Html::rawElement(
				'div',
				[ 'class' => 'mw-importfile-diff-view' ],
				$this->buildDiff( $this->importPlan->getInitialFileInfoText(), $newText )
			) .
			( new ImportIdentityFormSnippet( [
				'clientUrl' => $this->importPlan->getRequest()->getUrl(),
				'intendedFileName' => $this->importPlan->getFileName(),
				'intendedWikiText' => $this->importPlan->getFileInfoText(),
				'importDetailsHash' => $this->specialPage->getRequest()->getVal( 'importDetailsHash' ),
			] ) )->getHtml() .
			new ButtonInputWidget(
				[
					'label' => $this->specialPage->msg( 'fileimporter-to-preview' )->plain(),
					'type' => 'submit',
					'flags' => [ 'primary', 'progressive' ],
				]
			) .
			Html::closeElement( 'form' );
	}

	private function buildDiff( $originalText, $newText ) {
		$originalContent = ContentHandler::makeContent(
			$originalText,
			$this->importPlan->getTitle(),
			CONTENT_MODEL_WIKITEXT,
			CONTENT_FORMAT_WIKITEXT
		);
		$newContent = ContentHandler::makeContent(
			$newText,
			$this->importPlan->getTitle(),
			CONTENT_MODEL_WIKITEXT,
			CONTENT_FORMAT_WIKITEXT
		);

		$contenHandler = ContentHandler::getForModelID( CONTENT_MODEL_WIKITEXT );
		$diffEngine = $contenHandler->createDifferenceEngine( $this->specialPage->getContext() );
		$diffEngine->setContent( $originalContent, $newContent );

		$diffEngine->showDiffStyle();
		return $diffEngine->getDiff(
			$this->specialPage->msg( 'currentrev' ),
			$this->specialPage->msg( 'yourtext' )
		);
	}

}
