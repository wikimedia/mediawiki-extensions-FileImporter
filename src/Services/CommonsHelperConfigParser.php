<?php

namespace FileImporter\Services;

use FileImporter\Data\WikiTextConversions;
use FileImporter\Exceptions\LocalizedImportException;
use Message;

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
		$categorySection = $this->splitSectionsByHeaders( '== Categories ==', $this->wikiText );
		if ( !$categorySection ) {
			throw new LocalizedImportException(
				new Message( 'fileimporter-commonshelper-parsing-failed', [
					$this->commonsHelperConfigUrl, 'Categories'
				] )
			);
		}

		$templateSection = $this->splitSectionsByHeaders( '== Templates ==', $this->wikiText );
		if ( !$templateSection ) {
			throw new LocalizedImportException(
				new Message( 'fileimporter-commonshelper-parsing-failed', [
					$this->commonsHelperConfigUrl, 'Templates'
				] )
			);
		}

		$badCategorySection = $this->splitSectionsByHeaders( '=== Bad ===', $categorySection );
		if ( !$badCategorySection ) {
			throw new LocalizedImportException(
				new Message( 'fileimporter-commonshelper-parsing-failed', [
					$this->commonsHelperConfigUrl, 'Categories/Bad'
				] )
			);
		}

		$badTemplateSection = $this->splitSectionsByHeaders( '=== Bad ===', $templateSection );
		if ( !$badTemplateSection ) {
			throw new LocalizedImportException(
				new Message( 'fileimporter-commonshelper-parsing-failed', [
					$this->commonsHelperConfigUrl, 'Templates/Bad'
				] )
			);
		}

		return new WikiTextConversions(
			$this->getItemList( $badTemplateSection ),
			$this->getItemList( $badCategorySection )
		);
	}

	/**
	 * @param string $header
	 * @param string $text
	 *
	 * @return string|false
	 */
	private function splitSectionsByHeaders( $header, $text ) {
		$beginIndex = strpos( $text, $header );

		if ( $beginIndex === false ) {
			return false;
		}

		$beginIndex += strlen( $header );
		$headerDividerCount = substr_count( $header, '=' );

		$regexStr = '(\n'
			. str_repeat( '=', $headerDividerCount / 2 )
			. '\s[a-zA-Z ]*\s'
			. str_repeat( '=', $headerDividerCount / 2 )
			. '\n)';

		$regexRes = preg_match( $regexStr, $text, $matches, PREG_OFFSET_CAPTURE, $beginIndex );

		return ( $regexRes === 1 ) ?
			substr( $text, $beginIndex, $matches[0][1] - $beginIndex ) :
			substr( $text, $beginIndex );
	}

	/**
	 * @param string $text
	 *
	 * @return string[]
	 */
	private function getItemList( $text ) {
		$list = preg_split( '(\*\040)', str_replace( "\n", '', $text ) );
		return array_splice( $list, 1 );
	}

}
