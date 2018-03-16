<?php

namespace FileImporter\Html;

use File;
use Html;
use Message;

/**
 * Html showing a list of duplicate files.
 */
class DuplicateFilesPage {

	/**
	 * @var File[]
	 */
	private $files;

	/**
	 * @param File[] $files
	 */
	public function __construct(
		array $files
	) {
		$this->files = $files;
	}

	/**
	 * @return string
	 */
	public function getHtml() {
		$duplicateFilesList = '';
		$duplicatesMessage = ( new Message( 'fileimporter-duplicatefilesdetected-prefix' ) )->plain();
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

		return Html::rawElement(
			'div',
			[ 'class' => 'warningbox' ],
			Html::element( 'p', [], ( new Message( 'fileimporter-duplicatefilesdetected' ) )->plain() )
		) .
		$duplicateFilesList;
	}

}
