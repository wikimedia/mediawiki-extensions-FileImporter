<?php

namespace FileImporter\Data;

use InvalidArgumentException;

/**
 * Class holding validation and cleanup rules for the file description wikitext. This class is not
 * aware of the source of these rules. They can be extracted from CommonsHelper2-compatible
 * configuration files or other, yet to be defined sources.
 *
 * @license GPL-2.0-or-later
 */
class WikitextConversions {

	/**
	 * @var string[]
	 */
	private $headingReplacements = [];

	/**
	 * @var string[]
	 */
	private $goodTemplates = [];

	/**
	 * @var string[]
	 */
	private $badTemplates = [];

	/**
	 * @var string[]
	 */
	private $badCategories = [];

	/**
	 * @var string[]
	 */
	private $obsoleteTemplates = [];

	/**
	 * @var array[]
	 */
	private $transferTemplates = [];

	/**
	 * @param string[] $goodTemplates List of case-insensitive page names without namespace prefix
	 * @param string[] $badTemplates List of case-insensitive page names without namespace prefix
	 * @param string[] $badCategories List of case-insensitive page names without namespace prefix
	 * @param string[] $obsoleteTemplates List of case-insensitive page names without namespace prefix
	 * @param array[] $transferTemplates List mapping source template names without namespace prefix
	 *  to replacement rules in the following format:
	 *  string $sourceTemplate => [
	 *      'targetTemplate' => string,
	 *      'parameters' => [
	 *          string $targetParameter => [
	 *              'addIfMissing' => bool,
	 *              'addLanguageTemplate' => bool,
	 *              'sourceParameters' => string|string[],
	 *          ],
	 *          …
	 *      ],
	 *  ]
	 */
	public function __construct(
		array $goodTemplates,
		array $badTemplates,
		array $badCategories,
		array $obsoleteTemplates,
		array $transferTemplates
	) {
		foreach ( $goodTemplates as $pageName ) {
			$this->goodTemplates[$this->lowercasePageName( $pageName )] = true;
		}

		foreach ( $badTemplates as $pageName ) {
			$this->badTemplates[$this->lowercasePageName( $pageName )] = true;
		}

		foreach ( $badCategories as $pageName ) {
			$this->badCategories[$this->lowercasePageName( $pageName )] = true;
		}

		foreach ( $obsoleteTemplates as $pageName ) {
			$this->obsoleteTemplates[$this->lowercasePageName( $pageName )] = true;
		}

		foreach ( $transferTemplates as $from => $to ) {
			// TODO: Accepts strings for backwards-compatibility; remove if not needed any more
			if ( is_string( $to ) ) {
				$to = [ 'targetTemplate' => $to, 'parameters' => [] ];
			}

			if ( empty( $to['targetTemplate'] ) ) {
				throw new InvalidArgumentException( "$from transfer rule misses targetTemplate" );
			}
			if ( !isset( $to['parameters'] ) ) {
				throw new InvalidArgumentException( "$from transfer rule misses parameters" );
			}

			$from = $this->lowercasePageName( $from );
			$to['targetTemplate'] = $this->normalizePageName( $to['targetTemplate'] );
			$this->transferTemplates[$from] = $to;
		}
	}

	/**
	 * @param string[] $headingReplacements Straight 1:1 mapping of source to target headings
	 *  without any `==` syntax
	 */
	public function setHeadingReplacements( array $headingReplacements ) {
		$this->headingReplacements = $headingReplacements;
	}

	/**
	 * @param string $heading
	 *
	 * @return string
	 */
	public function swapHeading( $heading ) {
		return $this->headingReplacements[$heading] ?? $heading;
	}

	/**
	 * @param string $pageName Case-insensitive page name with the canonical English "Template:…"
	 *  prefix
	 *
	 * @return bool
	 */
	public function isTemplateGood( $pageName ) {
		$pageName = $this->removeNamespaceFromString( $pageName );
		return array_key_exists( $this->lowercasePageName( $pageName ), $this->goodTemplates );
	}

	/**
	 * @return bool
	 */
	public function hasGoodTemplates() {
		return $this->goodTemplates !== [];
	}

	/**
	 * @param string $pageName Case-insensitive page name with the canonical English "Template:…"
	 *  prefix
	 *
	 * @return bool
	 */
	public function isTemplateBad( $pageName ) {
		$pageName = $this->removeNamespaceFromString( $pageName );
		return array_key_exists( $this->lowercasePageName( $pageName ), $this->badTemplates );
	}

	/**
	 * @param string $pageName Case-insensitive page name with the canonical English "Category:…"
	 *  prefix
	 *
	 * @return bool
	 */
	public function isCategoryBad( $pageName ) {
		$pageName = $this->removeNamespaceFromString( $pageName );
		return array_key_exists( $this->lowercasePageName( $pageName ), $this->badCategories );
	}

	/**
	 * @param string $pageName
	 *
	 * @return bool
	 */
	public function isObsoleteTemplate( $pageName ) {
		$pageName = $this->removeNamespaceFromString( $pageName );
		return array_key_exists( $this->lowercasePageName( $pageName ), $this->obsoleteTemplates );
	}

	/**
	 * @param string $templateName
	 *
	 * @return string|false
	 */
	public function swapTemplate( $templateName ) {
		$templateName = $this->lowercasePageName( $templateName );
		return array_key_exists( $templateName, $this->transferTemplates )
			? $this->transferTemplates[$templateName]['targetTemplate']
			: false;
	}

	/**
	 * @param string $templateName
	 *
	 * @return string[] Array mapping source to target parameter names.
	 */
	public function getTemplateParameters( $templateName ) {
		$templateName = $this->lowercasePageName( $templateName );
		if ( !isset( $this->transferTemplates[$templateName] ) ) {
			return [];
		}

		$replacements = [];
		foreach ( $this->transferTemplates[$templateName]['parameters'] as $targetParameter => $opt ) {
			if ( !empty( $opt['sourceParameters'] ) ) {
				foreach ( (array)$opt['sourceParameters'] as $sourceParameter ) {
					$replacements[$sourceParameter] = $targetParameter;
				}
			}
		}
		return $replacements;
	}

	/**
	 * @param string $templateName
	 *
	 * @return string[] Array mapping required target parameter names to static string values.
	 */
	public function getRequiredTemplateParameters( $templateName ) {
		$templateName = $this->lowercasePageName( $templateName );
		if ( !isset( $this->transferTemplates[$templateName] ) ) {
			return [];
		}

		$additions = [];
		foreach ( $this->transferTemplates[$templateName]['parameters'] as $targetParameter => $opt ) {
			if ( isset( $opt['addIfMissing'] ) && $opt['addIfMissing'] ) {
				$additions[$targetParameter] = $opt['value'] ?? '';
			}
		}
		return $additions;
	}

	/**
	 * @param string $pageName
	 *
	 * @return string
	 */
	private function lowercasePageName( $pageName ) {
		return mb_convert_case( $this->normalizePageName( $pageName ), MB_CASE_LOWER );
	}

	/**
	 * @param string $pageName
	 *
	 * @return string
	 */
	private function normalizePageName( $pageName ) {
		return trim( str_replace( '_', ' ', $pageName ) );
	}

	/**
	 * @param string $title
	 *
	 * @return string
	 */
	private function removeNamespaceFromString( $title ) {
		$splitTitle = explode( ':', $title );
		return end( $splitTitle );
	}

}
