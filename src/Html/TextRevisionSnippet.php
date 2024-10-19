<?php

namespace FileImporter\Html;

use FileImporter\Data\TextRevision;
use MediaWiki\MediaWikiServices;
use MediaWiki\Parser\ParserOptions;
use MediaWiki\Title\Title;

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
	 */
	public function getHtml( TextRevision $textRevision, ?string $intendedWikitext ): string {
		$services = MediaWikiServices::getInstance();
		$title = Title::newFromText( $textRevision->getField( 'title' ), NS_FILE );

		$text = $intendedWikitext ?? $textRevision->getContent();

		$content = $services->getContentHandlerFactory()
			->getContentHandler( $textRevision->getContentModel() )
			->unserializeContent(
				$text,
				$textRevision->getContentFormat()
			);

		$parserOptions = new ParserOptions( $this->getUser(), $this->getLanguage() );
		$parserOptions->setIsPreview( true );

		$contentTransformer = $services->getContentTransformer();
		$content = $contentTransformer->preSaveTransform(
			$content,
			$title,
			$this->getUser(),
			$parserOptions
		);

		$contentRenderer = $services->getContentRenderer();
		$parseResult = $contentRenderer->getParserOutput(
			$content,
			$title,
			null,
			$parserOptions
		);

		return $parseResult->getText(
			[ 'enableSectionEditLinks' => false ]
		);
	}

}
