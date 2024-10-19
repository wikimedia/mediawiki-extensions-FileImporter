<?php

namespace FileImporter\Html;

use MediaWiki\Context\IContextSource;
use MediaWiki\Context\RequestContext;
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
class CategoriesSnippet {

	/** @var string[] */
	private array $visibleCategories;
	/** @var string[] */
	private array $hiddenCategories;

	private ILanguageConverter $languageConverter;
	private LinkRenderer $linkRenderer;
	private IContextSource $context;

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
	public function getHtml(): string {
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
