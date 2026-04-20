<?php

namespace FileImporter\Html;

use MediaWiki\Html\Html;
use MediaWiki\Language\ILanguageConverter;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\MediaWikiServices;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\Title;
use OOUI\IconWidget;

/**
 * @license GPL-2.0-or-later
 */
class CategoriesSnippet extends SpecialPageHtmlFragment {

	private ILanguageConverter $languageConverter;
	private LinkRenderer $linkRenderer;

	public function __construct(
		SpecialPage|SpecialPageHtmlFragment $specialPage,
	) {
		parent::__construct( $specialPage );
		$services = MediaWikiServices::getInstance();
		$this->languageConverter = $services
			->getLanguageConverterFactory()
			->getLanguageConverter( $services->getContentLanguage() );
		$this->linkRenderer = $services->getLinkRenderer();
	}

	/**
	 * Render categories in a format similar to OutputPage
	 *
	 * @param string[] $visibleCategories
	 * @param string[] $hiddenCategories
	 * @return string HTML rendering of categories box
	 */
	public function getHtml( array $visibleCategories, array $hiddenCategories ): string {
		$output = '';

		// TODO: Gracefully handle an empty list of categories, pending decisions about the desired
		// behavior.
		if ( !$visibleCategories && !$hiddenCategories ) {
			return Html::rawElement(
				'div',
				[],
				new IconWidget( [ 'icon' => 'info' ] )
					. ' '
					. $this->msg( 'fileimporter-category-encouragement' )->parse()
			);
		}

		$categoryLinks = $this->buildCategoryLinks( $visibleCategories );
		$hiddenCategoryLinks = $this->buildCategoryLinks( $hiddenCategories );

		if ( $categoryLinks ) {
			$output .= Html::rawElement(
				'div',
				[ 'class' => 'mw-normal-catlinks' ],
				$this->linkRenderer->makeLink(
					SpecialPage::getSafeTitleFor( 'Categories' ),
					$this->msg( 'pagecategories' )->numParams( count( $categoryLinks ) )
						->text()
				) .
				$this->msg( 'colon-separator' )->escaped() .
				Html::rawElement( 'ul', [], implode( '', $categoryLinks ) )
			);
		}

		if ( $hiddenCategoryLinks ) {
			$output .= Html::rawElement(
				'div',
				[ 'class' => 'mw-hidden-catlinks' ],
				$this->msg( 'hidden-categories' )
					->numParams( count( $hiddenCategoryLinks ) )->escaped() .
				$this->msg( 'colon-separator' )->escaped() .
				Html::rawElement( 'ul', [], implode( '', $hiddenCategoryLinks ) )
			);
		}

		$output = Html::rawElement( 'div', [ 'class' => 'catlinks' ], $output );

		return $output;
	}

	/**
	 * @param string[] $categories List of raw category names
	 * @return string[] List of HTML `li` tags each containing a local link to a category.
	 */
	private function buildCategoryLinks( array $categories ) {
		$categoryLinks = [];

		foreach ( $categories as $category ) {
			$originalCategory = $category;

			$title = Title::makeTitleSafe( NS_CATEGORY, $category );
			if ( !$title ) {
				continue;
			}

			$this->languageConverter->findVariantLink( $category, $title, true );
			if ( $category !== $originalCategory && array_key_exists( $category, $categories ) ) {
				continue;
			}

			$text = $title->getText();
			$categoryLinks[] = Html::rawElement( 'li', [], $this->linkRenderer->makeLink( $title,
				$text ) );
		}

		return $categoryLinks;
	}

}
