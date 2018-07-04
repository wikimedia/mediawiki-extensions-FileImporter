<?php

namespace FileImporter\Services;

use FileImporter\Data\WikiTextConversions;
use FileImporter\Exceptions\LocalizedImportException;
use InvalidArgumentException;

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

		// Scan for all level-2 headings first, relevant for properly prioritized error reporting
		$categorySection = $this->grepSection( $wikiText, '== Categories ==', 'Categories' );
		$templateSection = $this->grepSection( $wikiText, '== Templates ==', 'Templates' );
		$informationSection = $this->grepSection( $wikiText, '== Information ==', 'Information' );

		$badCategorySection = $this->grepSection( $categorySection, '=== Bad ===',
			'Categories/Bad' );
		$goodTemplateSection = $this->grepSection( $templateSection, '=== Good ===',
			'Templates/Good' );
		$badTemplateSection = $this->grepSection( $templateSection, '=== Bad ===',
			'Templates/Bad' );
		$obsoleteTemplates = $this->grepSection( $templateSection, '=== Remove ===',
			'Templates/Remove' );
		$transferTemplateSection = $this->grepSection( $templateSection, '=== Transfer ===',
			'Templates/Transfer' );
		$descriptionSection = $this->grepSection( $informationSection, '=== Description ===',
			'Information/Description' );
		$licensingSection = $this->grepSection( $informationSection, '=== Licensing ===',
			'Information/Licensing' );

		$conversions = new WikiTextConversions(
			$this->getItemList( $goodTemplateSection ),
			$this->getItemList( $badTemplateSection ),
			$this->getItemList( $badCategorySection ),
			$this->getItemList( $obsoleteTemplates ),
			$this->parseTransferList( $transferTemplateSection )
		);
		$conversions->setHeadingReplacements(
			array_fill_keys( $this->getItemList( $descriptionSection ), '{{int:filedesc}}' ) +
			array_fill_keys( $this->getItemList( $licensingSection ), '{{int:license-header}}' )
		);
		return $conversions;
	}

	/**
	 * @param string $wikiText
	 * @param string $header
	 * @param string $sectionName
	 *
	 * @throws LocalizedImportException if the section could not be found
	 * @return string
	 */
	private function grepSection( $wikiText, $header, $sectionName ) {
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

		if ( !preg_match( $regex, $wikiText, $matches ) ) {
			throw new LocalizedImportException( [
				'fileimporter-commonshelper-parsing-failed',
				$this->commonsHelperConfigUrl,
				$sectionName
			] );
		}

		return $matches[1];
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
