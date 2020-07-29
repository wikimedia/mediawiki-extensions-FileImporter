<?php

namespace FileImporter\Services;

use FileImporter\Data\WikitextConversions;
use FileImporter\Exceptions\CommunityPolicyException;

/**
 * This class checks a file description page for required and forbidden categories and templates. It
 * does not have any knowledge about the wikitext syntax.
 *
 * @license GPL-2.0-or-later
 */
class FileDescriptionPageValidator {

	/**
	 * @var WikitextConversions
	 */
	private $wikitextConversions;

	/**
	 * @param WikitextConversions $conversions
	 */
	public function __construct( WikitextConversions $conversions ) {
		$this->wikitextConversions = $conversions;
	}

	/**
	 * @param string[] $templates List of case-insensitive page names without namespace prefix
	 *
	 * @throws CommunityPolicyException
	 */
	public function validateTemplates( array $templates ) {
		foreach ( $templates as $template ) {
			if ( $this->wikitextConversions->isTemplateBad( $template ) ) {
				throw new CommunityPolicyException( [
					'fileimporter-file-contains-blocked-category-template',
					$template
				] );
			}
		}
	}

	/**
	 * @param string[] $categories List of case-insensitive page names without namespace prefix
	 *
	 * @throws CommunityPolicyException
	 */
	public function validateCategories( array $categories ) {
		foreach ( $categories as $category ) {
			if ( $this->wikitextConversions->isCategoryBad( $category ) ) {
				throw new CommunityPolicyException( [
					'fileimporter-file-contains-blocked-category-template',
					$category
				] );
			}
		}
	}

	/**
	 * @param string[] $templates List of case-insensitive page names without namespace prefix
	 *
	 * @throws CommunityPolicyException
	 */
	public function hasRequiredTemplate( array $templates ) {
		if ( !$this->wikitextConversions->hasGoodTemplates() ) {
			return;
		}

		foreach ( $templates as $template ) {
			if ( $this->wikitextConversions->isTemplateGood( $template ) ) {
				return;
			}
		}

		throw new CommunityPolicyException( [ 'fileimporter-file-missing-required-template' ] );
	}

}
