<?php

namespace FileImporter\Html;

use File;
use Html;
use OOUI\ButtonWidget;

/**
 * Html showing a list of duplicate files.
 *
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class DuplicateFilesErrorPage extends SpecialPageHtmlFragment {

	/**
	 * @param File[] $files
	 * @param string|null $url
	 *
	 * @return string
	 */
	public function getHtml( array $files, $url ) {
		$duplicateFilesList = '';
		$duplicatesMessage = wfMessage( 'fileimporter-duplicatefilesdetected-prefix' )->plain();
		$duplicateFilesList .= Html::rawElement(
			'p',
			[],
			Html::element( 'strong', [], $duplicatesMessage )
		);
		$duplicateFilesList .= Html::openElement( 'ul' );
		foreach ( $files as $file ) {
			$duplicateFilesList .= Html::rawElement(
				'li',
				[],
				Html::element(
					'a',
					[ 'href' => $file->getTitle()->getInternalURL() ],
					$file->getTitle()
				)
			);
		}
		$duplicateFilesList .= Html::closeElement( 'ul' );

		$output = Html::rawElement(
				'div',
				[ 'class' => 'mw-importfile-error-banner errorbox' ],
				Html::element( 'p', [], wfMessage( 'fileimporter-duplicatefilesdetected' )->plain() )
			);

		$output .= $duplicateFilesList;

		if ( $url !== null ) {
			$output .= new ButtonWidget(
				[
					'label' => wfMessage( 'fileimporter-go-to-original-file-button' )->plain(),
					'href' => $url,
					'classes' => [ 'mw-importfile-error-back-button' ],
					'flags' => [ 'primary', 'progressive' ]
				]
			);
		}

		return $output;
	}

}
