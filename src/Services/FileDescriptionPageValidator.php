<?php

namespace FileImporter\Services;

use FileImporter\Data\WikitextConversions;
use FileImporter\Exceptions\LocalizedImportException;
use MediaWiki\MediaWikiServices;

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
	 * @param string[] $templates List of case-insensitive page names without namespace prefix
	 *
	 * @throws LocalizedImportException
	 */
	public function validateTemplates( array $templates ) {
		foreach ( $templates as $template ) {
			if ( $this->wikitextConversions->isTemplateBad( $template ) ) {
				throw new LocalizedImportException( [
					'fileimporter-file-contains-blocked-category-template',
					$template,
					$this->siteName
				] );
			}
		}
	}

	/**
	 * @param string[] $categories List of case-insensitive page names without namespace prefix
	 *
	 * @throws LocalizedImportException
	 */
	public function validateCategories( array $categories ) {
		foreach ( $categories as $category ) {
			if ( $this->wikitextConversions->isCategoryBad( $category ) ) {
				throw new LocalizedImportException( [
					'fileimporter-file-contains-blocked-category-template',
					$category,
					$this->siteName
				] );
			}
		}
	}

	/**
	 * @param string[] $templates List of case-insensitive page names without namespace prefix
	 *
	 * @throws LocalizedImportException
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

		throw new LocalizedImportException( 'fileimporter-file-missing-required-template' );
	}

}
