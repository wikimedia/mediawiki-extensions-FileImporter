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

		$informationSection = $this->splitSectionsByHeaders( '== Information ==', $wikiText );
		if ( $informationSection === false ) {
			throw new LocalizedImportException(
				new Message( 'fileimporter-commonshelper-parsing-failed', [
					$this->commonsHelperConfigUrl, 'Information'
				] )
			);
		}

		$goodTemplateSection = $this->splitSectionsByHeaders( '=== Good ===', $templateSection );
		if ( $goodTemplateSection === false ) {
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

		$transferTemplateSection = $this->splitSectionsByHeaders( '=== Transfer ===', $templateSection );
		if ( $transferTemplateSection === false ) {
			throw new LocalizedImportException(
				new Message( 'fileimporter-commonshelper-parsing-failed', [
					$this->commonsHelperConfigUrl, 'Templates/Transfer'
				] )
			);
		}

		$descriptionSection = $this->splitSectionsByHeaders( '=== Description ===', $informationSection );
		if ( $descriptionSection === false ) {
			throw new LocalizedImportException(
				new Message( 'fileimporter-commonshelper-parsing-failed', [
					$this->commonsHelperConfigUrl, 'Information/Description'
				] )
			);
		}

		$licensingSection = $this->splitSectionsByHeaders( '=== Licensing ===', $informationSection );
		if ( $licensingSection === false ) {
			throw new LocalizedImportException(
				new Message( 'fileimporter-commonshelper-parsing-failed', [
					$this->commonsHelperConfigUrl, 'Information/Licensing'
				] )
			);
		}

		$conversions = new WikiTextConversions(
			$this->getItemList( $goodTemplateSection ),
			$this->getItemList( $badTemplateSection ),
			$this->getItemList( $badCategorySection ),
			$this->parseTransferList( $transferTemplateSection )
		);
		$conversions->setHeadingReplacements(
			array_fill_keys( $this->getItemList( $descriptionSection ), '{{int:filedesc}}' ) +
			array_fill_keys( $this->getItemList( $licensingSection ), '{{int:license-header}}' )
		);
		return $conversions;
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

	/**
	 * @param string $wikiText
	 *
	 * @return array[]
	 */
	private function parseTransferList( $wikiText ) {
		$transfers = [];

		preg_match_all(
			'/^;\h*+([^:|\n]+)\n?:\h*+([^:|\n]+)(.*)/m',
			$wikiText,
			$patterns,
			PREG_SET_ORDER
		);
		foreach ( $patterns as $pattern ) {
			list( , $sourceTemplate, $targetTemplate, $paramPatterns ) = $pattern;
			$parameterTransfers = [];

			$paramPatterns = preg_split( '/\s*\|+\s*/', $paramPatterns, -1, PREG_SPLIT_NO_EMPTY );
			foreach ( $paramPatterns as $paramPattern ) {
				list( $targetParam, $sourceParam ) = preg_split( '/\s*=\s*/', $paramPattern, 2 );

				// TODO: The magic words "%AUTHOR%" and "%TRANSFERUSER%" are not supported yet
				if ( strpos( $sourceParam, '%' ) !== false ) {
					continue;
				}

				preg_match( '/^(?:(\+)|(@))?(.*)/', $targetParam, $matches );
				list( , $addIfMissing, $addLanguageTemplate, $targetParam ) = $matches;

				$parameterTransfers[$targetParam] = [
					'addIfMissing' => (bool)$addIfMissing,
					'addLanguageTemplate' => (bool)$addLanguageTemplate,
				];
				if ( $addIfMissing ) {
					$parameterTransfers[$targetParam]['value'] = $sourceParam;
				} else {
					$parameterTransfers[$targetParam]['sourceParameters'] = $sourceParam;
				}
			}

			$transfers[$sourceTemplate] = [
				'targetTemplate' => $targetTemplate,
				'parameters' => $parameterTransfers,
			];
		}

		return $transfers;
	}

}
