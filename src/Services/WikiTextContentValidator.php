<?php

namespace FileImporter\Services;

use FileImporter\Data\WikiTextConversions;
use FileImporter\Exceptions\LocalizedImportException;
use MediaWiki\MediaWikiServices;
use Message;

class WikiTextContentValidator {

	/**
	 * @var WikiTextConversions
	 */
	private $wikiTextConversions;

	/**
	 * @var string
	 */
	private $siteName;

	/**
	 * @param WikiTextConversions $wikiTextConversions
	 */
	public function __construct( WikiTextConversions $wikiTextConversions ) {
		$this->wikiTextConversions = $wikiTextConversions;
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
			$templateTitle = $template['title'];
			if ( $this->wikiTextConversions->isTemplateBad( $templateTitle ) ) {
				throw new LocalizedImportException(
					new Message(
						'fileimporter-file-contains-blocked-category-template',
						[ $templateTitle, $this->siteName ]
					)
				);
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
			$categoryTitle = $category['title'];
			if ( $this->wikiTextConversions->isCategoryBad( $categoryTitle ) ) {
				throw new LocalizedImportException(
					new Message(
						'fileimporter-file-contains-blocked-category-template',
						[ $categoryTitle, $this->siteName ]
					)
				);
			}
		}
	}

	/**
	 * @param array[] $templates
	 *
	 * @throws LocalizedImportException
	 */
	public function hasRequiredTemplate( array $templates ) {
		if ( !$this->wikiTextConversions->hasGoodTemplates() ) {
			return;
		}

		foreach ( $templates as $template ) {
			$templateTitle = $template['title'];
			if ( $this->wikiTextConversions->isTemplateGood( $templateTitle ) ) {
				return;
			}
		}

		throw new LocalizedImportException(
			new Message(
				'fileimporter-file-missing-required-template'
			)
		);
	}

}
