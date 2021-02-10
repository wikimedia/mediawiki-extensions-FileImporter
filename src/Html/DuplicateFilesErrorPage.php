<?php

namespace FileImporter\Html;

use File;
use Html;
use OOUI\ButtonWidget;
use OOUI\MessageWidget;

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
		$output = new MessageWidget( [
			'label' => $this->msg( 'fileimporter-duplicatefilesdetected' )->plain(),
			'type' => 'error',
		] );

		$output .= Html::rawElement( 'p', [], Html::element( 'strong', [],
			$this->msg( 'fileimporter-duplicatefilesdetected-prefix' )->plain()
		) );

		$duplicateFilesList = '';
		foreach ( $files as $file ) {
			$duplicateFilesList .= Html::rawElement( 'li', [], Html::element(
				'a',
				[ 'href' => $file->getTitle()->getInternalURL() ],
				$file->getTitle()
			) );
		}

		$output .= Html::rawElement( 'ul', [], $duplicateFilesList );

		if ( $url ) {
			$output .= Html::element( 'br' ) .
			new ButtonWidget(
				[
					'label' => $this->msg( 'fileimporter-go-to-original-file-button' )->plain(),
					'href' => $url,
					'classes' => [ 'mw-importfile-error-back-button' ],
					'flags' => [ 'primary', 'progressive' ]
				]
			);
		}

		return $output;
	}

}
