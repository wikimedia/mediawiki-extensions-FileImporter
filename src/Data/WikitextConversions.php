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

	public const REQUIRED_TEMPLATES = 'requiredTemplates';
	public const FORBIDDEN_TEMPLATES = 'forbiddenTemplates';
	public const OBSOLETE_TEMPLATES = 'obsoleteTemplates';
	public const TEMPLATE_TRANSFORMATIONS = 'templateTransformations';
	public const FORBIDDEN_CATEGORIES = 'forbiddenCategories';
	public const HEADING_REPLACEMENTS = 'headingReplacements';

	/**
	 * @var string[]
	 */
	private $headingReplacements = [];

	/**
	 * @var true[] A string => true map for performance reasons
	 */
	private $goodTemplates = [];

	/**
	 * @var true[] A string => true map for performance reasons
	 */
	private $badTemplates = [];

	/**
	 * @var true[] A string => true map for performance reasons
	 */
	private $badCategories = [];

	/**
	 * @var true[] A string => true map for performance reasons
	 */
	private $obsoleteTemplates = [];

	/**
	 * @var array[]
	 */
	private $transferTemplates = [];

	/**
	 * @param array[] $conversions A nested array structure in the following format:
	 * [
	 *     self::REQUIRED_TEMPLATES => string[] List of case-insensitive page names without
	 *         namespace prefix
	 *     self::FORBIDDEN_TEMPLATES => string[] List of case-insensitive page names without
	 *         namespace prefix
	 *     self::OBSOLETE_TEMPLATES => string[] List of case-insensitive page names without
	 *         namespace prefix
	 *     self::TEMPLATE_TRANSFORMATIONS => array[] List mapping source template names without
	 *         namespace prefix to replacement rules in the following format:
	 *         string $sourceTemplate => [
	 *             'targetTemplate' => string
	 *             'parameters' => [
	 *                 string $targetParameter => [
	 *                     'addIfMissing' => bool
	 *                     'addLanguageTemplate' => bool
	 *                     'sourceParameters' => string[]|string
	 *                 ],
	 *                 …
	 *             ]
	 *         ],
	 *         …
	 *     self::FORBIDDEN_CATEGORIES => string[] List of case-insensitive page names without
	 *         namespace prefix
	 *     self::HEADING_REPLACEMENTS => string[] Straight 1:1 mapping of source to target headings
	 *         without any `==` syntax
	 * ]
	 *
	 * @throws InvalidArgumentException if the input format misses expected fields. This should be
	 *  unreachable, as the only provider is the CommonsHelperConfigParser.
	 */
	public function __construct( array $conversions ) {
		// FIXME: Backwards-compatibility with the old signature, still used in some tests. Remove
		// when not needed any more.
		if ( func_num_args() > 1 ) {
			[ $goodTemplates, $badTemplates, $badCategories, $obsoleteTemplates,
				$transferTemplates ] = func_get_args();
		} else {
			$goodTemplates = $conversions[self::REQUIRED_TEMPLATES] ?? [];
			$badTemplates = $conversions[self::FORBIDDEN_TEMPLATES] ?? [];
			$obsoleteTemplates = $conversions[self::OBSOLETE_TEMPLATES] ?? [];
			$transferTemplates = $conversions[self::TEMPLATE_TRANSFORMATIONS] ?? [];
			$badCategories = $conversions[self::FORBIDDEN_CATEGORIES] ?? [];
			$this->headingReplacements = $conversions[self::HEADING_REPLACEMENTS] ?? [];
		}

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
	 * @param string $heading
	 *
	 * @return string
	 */
	public function swapHeading( $heading ) {
		return $this->headingReplacements[$heading] ?? $heading;
	}

	/**
	 * @param string $pageName Case-insensitive page name. The namespace is ignored. Titles like
	 *  "Template:A" and "User:A" are considered equal.
	 *
	 * @return bool
	 */
	public function isTemplateGood( $pageName ) {
		$pageName = $this->removeNamespace( $pageName );
		return array_key_exists( $this->lowercasePageName( $pageName ), $this->goodTemplates );
	}

	/**
	 * @return bool
	 */
	public function hasGoodTemplates() {
		return $this->goodTemplates !== [];
	}

	/**
	 * @param string $pageName Case-insensitive page name. The namespace is ignored. Titles like
	 *  "Template:A" and "User:A" are considered equal.
	 *
	 * @return bool
	 */
	public function isTemplateBad( $pageName ) {
		$pageName = $this->removeNamespace( $pageName );
		return array_key_exists( $this->lowercasePageName( $pageName ), $this->badTemplates );
	}

	/**
	 * @param string $pageName Case-insensitive page name. The namespace is ignored. Titles like
	 *  "Category:A" and "User:A" are considered equal.
	 *
	 * @return bool
	 */
	public function isCategoryBad( $pageName ) {
		$pageName = $this->removeNamespace( $pageName );
		return array_key_exists( $this->lowercasePageName( $pageName ), $this->badCategories );
	}

	/**
	 * @param string $pageName Case-insensitive page name. Prefixes are significant.
	 *
	 * @return bool
	 */
	public function isObsoleteTemplate( $pageName ) {
		return array_key_exists( $this->lowercasePageName( $pageName ), $this->obsoleteTemplates );
	}

	/**
	 * @param string $templateName Case-insensitive page name. Prefixes are significant.
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
	 * @param string $templateName Case-insensitive page name. Prefixes are significant.
	 *
	 * @return array[] Array mapping source to target parameters:
	 * [
	 *     string $source => [
	 *          'target' => string Target parameter name
	 *          'addLanguageTemplate' => bool Whether or not to add a template like {{de|…}}
	 *     ],
	 *     …
	 * ]
	 */
	public function getTemplateParameters( $templateName ) {
		$templateName = $this->lowercasePageName( $templateName );
		if ( !isset( $this->transferTemplates[$templateName] ) ) {
			return [];
		}

		$replacements = [];
		foreach ( $this->transferTemplates[$templateName]['parameters'] as $targetParameter => $opt ) {
			$sourceParameters = (array)( $opt['sourceParameters'] ?? [] );
			$addLanguageTemplate = (bool)( $opt['addLanguageTemplate'] ?? false );

			foreach ( $sourceParameters as $sourceParameter ) {
				if ( $sourceParameter !== '' ) {
					$replacements[$sourceParameter] = [
						'target' => $targetParameter,
						'addLanguageTemplate' => $addLanguageTemplate
					];
				}
			}
		}
		return $replacements;
	}

	/**
	 * @param string $templateName Case-insensitive page name. Prefixes are significant.
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
			$addIfMissing = $opt['addIfMissing'] ?? false;
			if ( $addIfMissing ) {
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
	private function removeNamespace( $title ) {
		$splitTitle = explode( ':', $title, 2 );
		return end( $splitTitle );
	}

}
