<?php

namespace FileImporter\Services;

use FileImporter\Data\WikiTextConversions;
use FileImporter\Exceptions\LocalizedImportException;
use InvalidArgumentException;
use Message;

/**
 * @license GPL-2.0-or-later
 */
class CommonsHelperConfigParser {

	/**
	 * @var string
	 */
	private $commonsHelperConfigUrl;

	/**
	 * @var string
	 */
	private $wikiText;

	/**
	 * @param string $commonsHelperConfigUrl
	 * @param string $wikiText
	 */
	public function __construct( $commonsHelperConfigUrl, $wikiText ) {
		$this->commonsHelperConfigUrl = $commonsHelperConfigUrl;
		$this->wikiText = $wikiText;
	}

	/**
	 * @throws LocalizedImportException
	 * @return WikiTextConversions
	 */
	public function getWikiTextConversions() {
		// HTML comments must be removed first
		$wikiText = preg_replace( '/<!--.*?-->/s', '', $this->wikiText );

		$categorySection = $this->splitSectionsByHeaders( '== Categories ==', $wikiText );
		if ( $categorySection === false ) {
			throw new LocalizedImportException(
				new Message( 'fileimporter-commonshelper-parsing-failed', [
					$this->commonsHelperConfigUrl, 'Categories'
				] )
			);
		}

		$templateSection = $this->splitSectionsByHeaders( '== Templates ==', $wikiText );
		if ( $templateSection === false ) {
			throw new LocalizedImportException(
				new Message( 'fileimporter-commonshelper-parsing-failed', [
					$this->commonsHelperConfigUrl, 'Templates'
				] )
			);
		}

		$goodTemplateSection = $this->splitSectionsByHeaders( '=== Good ===', $templateSection );
		if ( !$goodTemplateSection ) {
			throw new LocalizedImportException(
				new Message( 'fileimporter-commonshelper-parsing-failed', [
					$this->commonsHelperConfigUrl, 'Templates/Good'
				] )
			);
		}

		$badCategorySection = $this->splitSectionsByHeaders( '=== Bad ===', $categorySection );
		if ( $badCategorySection === false ) {
			throw new LocalizedImportException(
				new Message( 'fileimporter-commonshelper-parsing-failed', [
					$this->commonsHelperConfigUrl, 'Categories/Bad'
				] )
			);
		}

		$badTemplateSection = $this->splitSectionsByHeaders( '=== Bad ===', $templateSection );
		if ( $badTemplateSection === false ) {
			throw new LocalizedImportException(
				new Message( 'fileimporter-commonshelper-parsing-failed', [
					$this->commonsHelperConfigUrl, 'Templates/Bad'
				] )
			);
		}

		return new WikiTextConversions(
			$this->getItemList( $goodTemplateSection ),
			$this->getItemList( $badTemplateSection ),
			$this->getItemList( $badCategorySection )
		);
	}

	/**
	 * @param string $header
	 * @param string $wikiText
	 *
	 * @return string|false
	 */
	private function splitSectionsByHeaders( $header, $wikiText ) {
		$level = strpos( $header, '= ' );
		if ( $level === false ) {
			throw new InvalidArgumentException( '$header must follow this format: "== … =="' );
		}
		$level++;

		// NOTE: This relaxes the parser to a degree that accepts "== Foobar ==" when
		// "== Foo bar ==" is requested.
		$headerRegex = str_replace( ' ', '\h*', preg_quote( $header, '/' ) );

		// Extract a section from the given wikitext blob. Start from the given 2nd- or 3rd-level
		// header. Stop at the same or a higher level (less equal signs), or at the end of the text.
		$regex = '/^' . $headerRegex . '\h*$(.*?)(?=^={1,' . $level . '}[^=]|\Z)/ms';
		return preg_match( $regex, $wikiText, $matches ) ? $matches[1] : false;
	}

	/**
	 * @param string $wikiText
	 *
	 * @return string[]
	 */
	private function getItemList( $wikiText ) {
		// Extract non-empty first-level list elements, exclude 2nd and deeper levels
		preg_match_all( '/^\*\h*([^\s*#:;].*?)\h*$/m', $wikiText, $matches );
		return $matches[1];
	}

}
