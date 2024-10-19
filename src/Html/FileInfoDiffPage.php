<?php

namespace FileImporter\Html;

use FileImporter\Data\ImportPlan;
use MediaWiki\Content\ContentHandler;
use MediaWiki\Html\Html;
use OOUI\ButtonInputWidget;

/**
 * Page to display a diff from changed file info text.
 *
 * @license GPL-2.0-or-later
 * @author Christoph Jauera <christoph.jauera@wikimedia.de>
 */
class FileInfoDiffPage extends SpecialPageHtmlFragment {

	public function getHtml( ImportPlan $importPlan, ContentHandler $contentHandler ): string {
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
				$this->buildDiff(
					$importPlan->getInitialFileInfoText(),
					$newText,
					$contentHandler
				)
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
	 * @return string HTML
	 */
	private function buildDiff( string $originalText, string $newText, ContentHandler $contentHandler ): string {
		$originalContent = $contentHandler->unserializeContent( $originalText );
		$newContent = $contentHandler->unserializeContent( $newText );

		$diffEngine = $contentHandler->createDifferenceEngine( $this->getContext() );
		$diffEngine->setContent( $originalContent, $newContent );

		$diffEngine->showDiffStyle();
		return $diffEngine->getDiff(
			$this->msg( 'currentrev' )->parse(),
			$this->msg( 'yourtext' )->parse()
		);
	}

}
