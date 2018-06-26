<?php

namespace FileImporter\Data;

/**
 * Class holding validation and replacement rules for the file description wikitext. This class is
 * not aware of the source of these rules. They can be extracted from CommonsHelper2 configuration
 * files or other, yet to be defined sources.
 *
 * @license GPL-2.0-or-later
 */
class WikiTextConversions {

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
	private $transferTemplates = [];

	/**
	 * @param string[] $goodTemplates List of case-insensitive page names without namespace prefix
	 * @param string[] $badTemplates List of case-insensitive page names without namespace prefix
	 * @param string[] $badCategories List of case-insensitive page names without namespace prefix
	 * @param string[] $transferTemplates List of template to template mappings without template format
	 */
	public function __construct(
		array $goodTemplates,
		array $badTemplates,
		array $badCategories,
		array $transferTemplates
	) {
		foreach ( $goodTemplates as $pageName ) {
			$this->goodTemplates[$this->normalizePageName( $pageName )] = true;
		}

		foreach ( $badTemplates as $pageName ) {
			$this->badTemplates[$this->normalizePageName( $pageName )] = true;
		}

		foreach ( $badCategories as $pageName ) {
			$this->badCategories[$this->normalizePageName( $pageName )] = true;
		}

		foreach ( $transferTemplates as $from => $to ) {
			$from = $this->normalizePageName( $from );
			// TODO: Normalize the replacement the same way, but don't lowercase it.
			$this->transferTemplates[$from] = $to;
		}
	}

	/**
	 * @param string $pageName Case-insensitive page name with the canonical English "Template:…"
	 *  prefix
	 *
	 * @return bool
	 */
	public function isTemplateGood( $pageName ) {
		$pageName = $this->removeNamespaceFromString( $pageName );
		return array_key_exists( $this->normalizePageName( $pageName ), $this->goodTemplates );
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
		return array_key_exists( $this->normalizePageName( $pageName ), $this->badTemplates );
	}

	/**
	 * @param string $pageName Case-insensitive page name with the canonical English "Category:…"
	 *  prefix
	 *
	 * @return bool
	 */
	public function isCategoryBad( $pageName ) {
		$pageName = $this->removeNamespaceFromString( $pageName );
		return array_key_exists( $this->normalizePageName( $pageName ), $this->badCategories );
	}

	/**
	 * @param string $templateName
	 *
	 * @return string|false
	 */
	public function swapTemplate( $templateName ) {
		$templateName = $this->normalizePageName( $templateName );
		return array_key_exists( $templateName, $this->transferTemplates )
			? $this->transferTemplates[$templateName]
			: false;
	}

	/**
	 * @param string $pageName
	 *
	 * @return string
	 */
	private function normalizePageName( $pageName ) {
		return mb_convert_case( trim( str_replace( '_', ' ', $pageName ) ), MB_CASE_LOWER );
	}

	/**
	 * @param string $title
	 *
	 * @return string
	 */
	private function removeNamespaceFromString( $title ) {
		$splitTitle = explode( ':', $title, 2 );
		return array_pop( $splitTitle );
	}

}
