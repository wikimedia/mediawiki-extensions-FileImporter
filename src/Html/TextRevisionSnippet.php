<?php

namespace FileImporter\Html;

use ContentHandler;
use FileImporter\Data\TextRevision;
use ParserOptions;
use Title;

/**
 * Html of parsed wikitext
 *
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class TextRevisionSnippet {

	/**
	 * @var TextRevision
	 */
	private $textRevision;

	/**
	 * @var string|null
	 */
	private $intendedWikiText;

	/**
	 * @param TextRevision $textRevision Latest test revision
	 * @param string|null $intendedWikiText This will override the text provided in the TextRevision
	 */
	public function __construct( TextRevision $textRevision, $intendedWikiText ) {
		$this->textRevision = $textRevision;
		$this->intendedWikiText = $intendedWikiText;
	}

	/**
	 * @return string
	 */
	public function getHtml() {
		$textRevision = $this->textRevision;
		$title = Title::newFromText( $textRevision->getField( 'title' ), NS_FILE );

		if ( $this->intendedWikiText === null ) {
			$text = $textRevision->getField( '*' );
		} else {
			$text = $this->intendedWikiText;
		}

		$content = ContentHandler::makeContent(
			$text,
			$title,
			$textRevision->getField( 'contentmodel' ),
			$textRevision->getField( 'contentformat' )
		);

		$parseResult = $content->getParserOutput(
			$title,
			null,
			new ParserOptions(),
			true
		);

		return $parseResult->getText(
			[ 'enableSectionEditLinks' => false ]
		);
	}

}
