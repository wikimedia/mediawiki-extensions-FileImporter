<?php

namespace FileImporter\Html;

use ContentHandler;
use FileImporter\Generic\Data\TextRevision;
use MWContentSerializationException;
use ParserOptions;
use Title;

class TextRevisionSnippet {

	/**
	 * @var TextRevision
	 */
	private $textRevision;

	public function __construct( TextRevision $textRevision ) {
		$this->textRevision = $textRevision;
	}

	public function getHtml() {
		$textRevision = $this->textRevision;
		$title = Title::newFromText( $textRevision->getField( 'title' ), NS_FILE );

		$content = null;
		try {
			$content = ContentHandler::makeContent(
				$textRevision->getField( '*' ),
				$title,
				$textRevision->getField( 'contentmodel' ),
				$textRevision->getField( 'contentformat' )
			);
		} catch ( MWContentSerializationException $ex ) {
			die( 'failed to parse content of latest revision' );
		}

		$parseResult = $content->getParserOutput(
			$title,
			null,
			$this->getParserOptions(),
			true
		);

		return $parseResult->getText();
	}

	private function getParserOptions() {
		$parserOptions = new ParserOptions();
		$parserOptions->setEditSection( false );
		return $parserOptions;
	}

}
