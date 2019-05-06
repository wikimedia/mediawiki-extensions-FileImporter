<?php

namespace FileImporter\Services;

use FileImporter\Data\WikitextConversions;
use FileImporter\Exceptions\LocalizedImportException;
use MediaWiki\MediaWikiServices;

/**
 * @fixme This class should be renamed. It is not about WikitextContent, not even about wikitext.
 * What it does is processing the structured information as returned by the "templates" as well as
 * "categories" API. As such, it probably belongs in the FileImporter\Remote\MediaWiki namespace.
 *
 * @license GPL-2.0-or-later
 */
class WikitextContentValidator {

	/**
	 * @var WikitextConversions
	 */
	private $wikitextConversions;

	/**
	 * @var string
	 */
	private $siteName;

	/**
	 * @param WikitextConversions $conversions
	 */
	public function __construct( WikitextConversions $conversions ) {
		$this->wikitextConversions = $conversions;
		// TODO: Inject
		$this->siteName = MediaWikiServices::getInstance()->getMainConfig()->get( 'Sitename' );
	}

	/**
	 * @param array[] $templates
	 *
	 * @throws LocalizedImportException
	 */
	public function validateTemplates( array $templates ) {
		foreach ( $templates as $template ) {
			if ( $template['ns'] !== NS_TEMPLATE ) {
				continue;
			}

			$templateTitle = $template['title'];
			if ( $this->wikitextConversions->isTemplateBad( $templateTitle ) ) {
				throw new LocalizedImportException( [
					'fileimporter-file-contains-blocked-category-template',
					$templateTitle,
					$this->siteName
				] );
			}
		}
	}

	/**
	 * @param array[] $categories
	 *
	 * @throws LocalizedImportException
	 */
	public function validateCategories( array $categories ) {
		foreach ( $categories as $category ) {
			if ( $category['ns'] !== NS_CATEGORY ) {
				continue;
			}

			$categoryTitle = $category['title'];
			if ( $this->wikitextConversions->isCategoryBad( $categoryTitle ) ) {
				throw new LocalizedImportException( [
					'fileimporter-file-contains-blocked-category-template',
					$categoryTitle,
					$this->siteName
				] );
			}
		}
	}

	/**
	 * @param array[] $templates
	 *
	 * @throws LocalizedImportException
	 */
	public function hasRequiredTemplate( array $templates ) {
		if ( !$this->wikitextConversions->hasGoodTemplates() ) {
			return;
		}

		foreach ( $templates as $template ) {
			if ( $template['ns'] !== NS_TEMPLATE ) {
				continue;
			}

			$templateTitle = $template['title'];
			if ( $this->wikitextConversions->isTemplateGood( $templateTitle ) ) {
				return;
			}
		}

		throw new LocalizedImportException( 'fileimporter-file-missing-required-template' );
	}

}
