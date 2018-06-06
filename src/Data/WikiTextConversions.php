<?php

namespace FileImporter\Data;

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
	 * @param string[] $goodTemplates List of case-insensitive page names without namespace prefix
	 * @param string[] $badTemplates List of case-insensitive page names without namespace prefix
	 * @param string[] $badCategories List of case-insensitive page names without namespace prefix
	 */
	public function __construct(
		array $goodTemplates,
		array $badTemplates,
		array $badCategories
	) {
		foreach ( $goodTemplates as $pageName ) {
			// FIXME: Avoid hard-coding the namespace name here
			$this->goodTemplates[$this->normalizePageName( 'Template:' . $pageName )] = true;
		}

		foreach ( $badTemplates as $pageName ) {
			// FIXME: Avoid hard-coding the namespace name here
			$this->badTemplates[$this->normalizePageName( 'Template:' . $pageName )] = true;
		}

		foreach ( $badCategories as $pageName ) {
			// FIXME: Avoid hard-coding the namespace name here
			$this->badCategories[$this->normalizePageName( 'Category:' . $pageName )] = true;
		}
	}

	/**
	 * @param string $pageName Case-insensitive page name with the canonical English "Template:…"
	 *  prefix
	 *
	 * @return bool
	 */
	public function isTemplateGood( $pageName ) {
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
		return array_key_exists( $this->normalizePageName( $pageName ), $this->badTemplates );
	}

	/**
	 * @param string $pageName Case-insensitive page name with the canonical English "Category:…"
	 *  prefix
	 *
	 * @return bool
	 */
	public function isCategoryBad( $pageName ) {
		return array_key_exists( $this->normalizePageName( $pageName ), $this->badCategories );
	}

	/**
	 * @param string $pageName
	 *
	 * @return string
	 */
	private function normalizePageName( $pageName ) {
		return mb_convert_case( trim( str_replace( '_', ' ', $pageName ) ), MB_CASE_LOWER );
	}

}
