<?php

namespace FileImporter\Html;

use FileImporter\Data\ImportPlan;
use OOUI\ButtonWidget;
use Html;

/**
 * Page displaying a successful import.
 *
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class ImportSuccessPage extends SpecialPageHtmlFragment {

	/**
	 * @param ImportPlan $importPlan
	 *
	 * @return string
	 */
	public function getHtml( ImportPlan $importPlan ) {
		$sourceUrl = $importPlan->getRequest()->getUrl();
		$importTitle = $importPlan->getTitle();

		return Html::rawElement(
			'div',
			[ 'class' => 'mw-importfile-success-banner successbox' ],
			$this->msg(
				'fileimporter-imported-success-banner'
			)->rawParams(
				Html::element(
					'a',
					[ 'href' => $importTitle->getInternalURL() ],
					$importTitle->getPrefixedText()
				)
			)->escaped()
		) .
		Html::rawElement(
			'p',
			[],
			$this->msg( 'fileimporter-imported-change-template' )->parse()
		) .
		new ButtonWidget(
			[
				'classes' => [ 'mw-importfile-add-template-button' ],
				'label' => $this->msg( 'fileimporter-go-to-original-file-button' )->plain(),
				'href' => $sourceUrl->getUrl(),
				'flags' => [ 'primary', 'progressive' ],
			]
		);
	}

}
