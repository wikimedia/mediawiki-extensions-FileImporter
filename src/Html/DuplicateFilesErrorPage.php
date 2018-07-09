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
class DuplicateFilesErrorPage {

	/**
	 * @var File[]
	 */
	private $files;

	/**
	 * @var string|null
	 */
	private $url;

	/**
	 * @param File[] $files
	 * @param string|null $url
	 */
	public function __construct( array $files, $url ) {
		$this->files = $files;
		$this->url = $url;
	}

	/**
	 * @return string
	 */
	public function getHtml() {
		$duplicateFilesList = '';
		$duplicatesMessage = wfMessage( 'fileimporter-duplicatefilesdetected-prefix' )->plain();
		$duplicateFilesList .= Html::rawElement(
			'p',
			[],
			Html::element( 'strong', [], $duplicatesMessage )
		);
		$duplicateFilesList .= Html::openElement( 'ul' );
		foreach ( $this->files as $file ) {
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

		if ( $this->url !== null ) {
			$output .= new ButtonWidget(
				[
					'label' => wfMessage( 'fileimporter-go-to-original-file-button' )->plain(),
					'href' => $this->url,
					'classes' => [ 'mw-importfile-error-back-button' ],
					'flags' => [ 'primary', 'progressive' ]
				]
			);
		}

		return $output;
	}

}
