<?php

namespace FileImporter\Services;

use FileImporter\Data\WikitextConversions;
use FileImporter\Exceptions\LocalizedImportException;
use MediaWiki\MediaWikiServices;

/**
 * This class checks a file description page for required and forbidden categories and templates. It
 * does not have any knowledge about the wikitext syntax, but borrows parts of the JSON structure
 * the MediaWiki query API returns.
 *
 * @license GPL-2.0-or-later
 */
class FileDescriptionPageValidator {

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
	 * @param array[] $templates List of arrays, each containing the elements
	 *  [ 'ns' => int $namespaceId, 'title' => string $title ]. Remember that pages outside of the
	 *  Template namespace can be used as templates. Such are skipped.
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
	 * @param array[] $categories List of arrays, each containing the elements
	 *  [ 'ns' => int $namespaceId, 'title' => string $title ].
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
	 * @param array[] $templates List of arrays, each containing the elements
	 *  [ 'ns' => int $namespaceId, 'title' => string $title ]. Remember that pages outside of the
	 *  Template namespace can be used as templates. Such are skipped.
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
