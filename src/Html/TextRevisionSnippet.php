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
class TextRevisionSnippet extends SpecialPageHtmlFragment {

	/**
	 * @param TextRevision $textRevision Latest test revision
	 * @param string|null $intendedWikitext This will override the text provided in the TextRevision
	 *
	 * @return string
	 */
	public function getHtml( TextRevision $textRevision, $intendedWikitext ) {
		$title = Title::newFromText( $textRevision->getField( 'title' ), NS_FILE );

		if ( $intendedWikitext === null ) {
			$text = $textRevision->getField( '*' );
		} else {
			$text = $intendedWikitext;
		}

		$content = ContentHandler::makeContent(
			$text,
			$title,
			$textRevision->getField( 'contentmodel' ),
			$textRevision->getField( 'contentformat' )
		);

		$parserOptions = new ParserOptions( $this->getUser(), $this->getLanguage() );
		$parserOptions->setIsPreview( true );

		$content = $content->preSaveTransform( $title, $this->getUser(), $parserOptions );
		$parseResult = $content->getParserOutput(
			$title,
			null,
			$parserOptions
		);

		return $parseResult->getText(
			[ 'enableSectionEditLinks' => false ]
		);
	}

}
