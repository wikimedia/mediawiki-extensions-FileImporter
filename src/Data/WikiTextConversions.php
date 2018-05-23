<?php

namespace FileImporter\Data;

class WikiTextConversions {

	/**
	 * @var string[]
	 */
	private $badTemplates;

	/**
	 * @var string[]
	 */
	private $badCategories;

	/**
	 * @param string[] $badTemplates
	 * @param string[] $badCategories
	 */
	public function __construct(
		array $badTemplates,
		array $badCategories
	) {
		$this->badTemplates = $badTemplates;
		$this->badCategories = $badCategories;
	}

	/**
	 * @param string $template
	 *
	 * @return bool
	 */
	public function isTemplateBad( $template ) {
		return in_array( $template, $this->badTemplates );
	}

	/**
	 * @param string $category
	 *
	 * @return bool
	 */
	public function isCategoryBad( $category ) {
		return in_array( $category, $this->badCategories );
	}

}
