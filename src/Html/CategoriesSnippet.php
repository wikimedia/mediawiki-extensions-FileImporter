<?php

namespace FileImporter\Html;

use Html;
use IContextSource;
use ILanguageConverter;
use MediaWiki\MediaWikiServices;
use OOUI\IconWidget;
use RequestContext;
use SpecialPage;
use Title;

/**
 * @license GPL-2.0-or-later
 */
class CategoriesSnippet {

	/** @var string[] */
	private $visibleCategories;
	/** @var string[] */
	private $hiddenCategories;

	/** @var ILanguageConverter */
	private $languageConverter;

	/** @var \MediaWiki\Linker\LinkRenderer */
	private $linkRenderer;

	/** @var IContextSource */
	private $context;

	/**
	 * @param string[] $visibleCategories
	 * @param string[] $hiddenCategories
	 */
	public function __construct( array $visibleCategories, array $hiddenCategories ) {
		$this->visibleCategories = $visibleCategories;
		$this->hiddenCategories = $hiddenCategories;

		$services = MediaWikiServices::getInstance();
		$this->languageConverter = $services
			->getLanguageConverterFactory()
			->getLanguageConverter( $services->getContentLanguage() );
		$this->linkRenderer = $services->getLinkRenderer();
		$this->context = RequestContext::getMain();
	}

	/**
	 * Render categories in a format similar to OutputPage
	 *
	 * @return string HTML rendering of categories box
	 */
	public function getHtml() {
		$output = '';

		// TODO: Gracefully handle an empty list of categories, pending decisions about the desired
		// behavior.
		if ( $this->visibleCategories === [] && $this->hiddenCategories === [] ) {
			return Html::rawElement(
				'div',
				[],
				new IconWidget( [ 'icon' => 'info' ] )
					. ' '
					. $this->context->msg( 'fileimporter-category-encouragement' )->parse()
			);
		}

		$categoryLinks = $this->buildCategoryLinks( $this->visibleCategories );
		$hiddenCategoryLinks = $this->buildCategoryLinks( $this->hiddenCategories );

		if ( $categoryLinks ) {
			$output .= Html::rawElement(
				'div',
				[ 'class' => 'mw-normal-catlinks' ],
				$this->linkRenderer->makeLink(
					SpecialPage::getSafeTitleFor( 'Categories' ),
					$this->context->msg( 'pagecategories' )->numParams( count( $categoryLinks ) )
						->text()
				) .
				$this->context->msg( 'colon-separator' )->escaped() .
				Html::rawElement( 'ul', [], implode( '', $categoryLinks ) )
			);
		}

		if ( $hiddenCategoryLinks ) {
			$output .= Html::rawElement(
				'div',
				[ 'class' => 'mw-hidden-catlinks' ],
				$this->context->msg( 'hidden-categories' )
					->numParams( count( $hiddenCategoryLinks ) )->escaped() .
				$this->context->msg( 'colon-separator' )->escaped() .
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
