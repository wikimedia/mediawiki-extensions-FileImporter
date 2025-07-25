<?php

namespace FileImporter\Data;

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

	/** @var array<string,string> */
	private array $headingReplacements = [];
	/** @var array<string,true> A string => true map for performance reasons */
	private array $goodTemplates = [];
	/** @var array<string,true> A string => true map for performance reasons */
	private array $badTemplates = [];
	/** @var array<string,true> A string => true map for performance reasons */
	private array $badCategories = [];
	/** @var array<string,true> A string => true map for performance reasons */
	private array $obsoleteTemplates = [];
	/** @var array<string,array{targetTemplate: string, parameters: array[]}> */
	private array $transferTemplates = [];

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
	 * @throws \InvalidArgumentException if the input format misses expected fields. This should be
	 *  unreachable, as the only provider is the CommonsHelperConfigParser.
	 */
	public function __construct( array $conversions ) {
		$goodTemplates = $conversions[self::REQUIRED_TEMPLATES] ?? [];
		$badTemplates = $conversions[self::FORBIDDEN_TEMPLATES] ?? [];
		$obsoleteTemplates = $conversions[self::OBSOLETE_TEMPLATES] ?? [];
		$transferTemplates = $conversions[self::TEMPLATE_TRANSFORMATIONS] ?? [];
		$badCategories = $conversions[self::FORBIDDEN_CATEGORIES] ?? [];
		$this->headingReplacements = $conversions[self::HEADING_REPLACEMENTS] ?? [];

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
				throw new \InvalidArgumentException( "$from transfer rule misses targetTemplate" );
			}
			if ( !isset( $to['parameters'] ) ) {
				throw new \InvalidArgumentException( "$from transfer rule misses parameters" );
			}

			$from = $this->lowercasePageName( $from );
			$to['targetTemplate'] = $this->normalizePageName( $to['targetTemplate'] );
			$this->transferTemplates[$from] = $to;
		}
	}

	public function swapHeading( string $heading ): string {
		return $this->headingReplacements[$heading] ?? $heading;
	}

	/**
	 * @param string $pageName Case-insensitive page name. The namespace is ignored. Titles like
	 *  "Template:A" and "User:A" are considered equal.
	 */
	public function isTemplateGood( string $pageName ): bool {
		$pageName = $this->removeNamespace( $pageName );
		return array_key_exists( $this->lowercasePageName( $pageName ), $this->goodTemplates );
	}

	public function hasGoodTemplates(): bool {
		return $this->goodTemplates !== [];
	}

	/**
	 * @param string $pageName Case-insensitive page name. The namespace is ignored. Titles like
	 *  "Template:A" and "User:A" are considered equal.
	 */
	public function isTemplateBad( string $pageName ): bool {
		$pageName = $this->removeNamespace( $pageName );
		return array_key_exists( $this->lowercasePageName( $pageName ), $this->badTemplates );
	}

	/**
	 * @param string $pageName Case-insensitive page name. The namespace is ignored. Titles like
	 *  "Category:A" and "User:A" are considered equal.
	 */
	public function isCategoryBad( string $pageName ): bool {
		$pageName = $this->removeNamespace( $pageName );
		return array_key_exists( $this->lowercasePageName( $pageName ), $this->badCategories );
	}

	/**
	 * @param string $pageName Case-insensitive page name. Prefixes are significant.
	 */
	public function isObsoleteTemplate( string $pageName ): bool {
		return array_key_exists( $this->lowercasePageName( $pageName ), $this->obsoleteTemplates );
	}

	/**
	 * @param string $templateName Case-insensitive page name. Prefixes are significant.
	 */
	public function swapTemplate( string $templateName ): ?string {
		$templateName = $this->lowercasePageName( $templateName );
		return $this->transferTemplates[$templateName]['targetTemplate'] ?? null;
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
	public function getTemplateParameters( string $templateName ): array {
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
	public function getRequiredTemplateParameters( string $templateName ): array {
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

	private function lowercasePageName( string $pageName ): string {
		return mb_convert_case( $this->normalizePageName( $pageName ), MB_CASE_LOWER );
	}

	private function normalizePageName( string $pageName ): string {
		return trim( str_replace( '_', ' ', $pageName ) );
	}

	private function removeNamespace( string $title ): string {
		$splitTitle = explode( ':', $title, 2 );
		return end( $splitTitle );
	}

}
