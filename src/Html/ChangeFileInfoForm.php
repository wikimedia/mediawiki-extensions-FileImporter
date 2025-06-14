<?php

namespace FileImporter\Html;

use FileImporter\Data\ImportPlan;
use MediaWiki\Html\Html;
use OOUI\ButtonInputWidget;

/**
 * Form allowing the user to change the file info text.
 *
 * @license GPL-2.0-or-later
 * @author Christoph Jauera <christoph.jauera@wikimedia.de>
 */
class ChangeFileInfoForm extends SpecialPageHtmlFragment {

	public function getHtml( ImportPlan $importPlan ): string {
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
		ImportIdentityFormSnippet::newFromImportPlan( $importPlan, [ 'intendedWikitext' ] )
			->getHtml() .
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
