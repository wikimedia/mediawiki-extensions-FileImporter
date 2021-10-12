<?php

namespace FileImporter\Tests\Html;

use FileImporter\Html\CategoriesSnippet;
use MediaWikiIntegrationTestCase;
use OOUI\BlankTheme;
use OOUI\Theme;

/**
 * @covers \FileImporter\Html\CategoriesSnippet
 *
 * @license GPL-2.0-or-later
 */
class CategoriesSnippetTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();

		$this->setUserLang( 'qqx' );
		Theme::setSingleton( new BlankTheme() );
	}

	public function testGetHtml_uncategorized() {
		$categoriesSnippet = new CategoriesSnippet( [], [] );
		$html = $categoriesSnippet->getHtml();

		$this->assertStringContainsString( '(fileimporter-category-encouragement)', $html );
	}

	public function testGetHtml_hasOneCategory() {
		$category = 'Puppies ' . mt_rand();
		$categoriesSnippet = new CategoriesSnippet( [ $category ], [] );
		$html = $categoriesSnippet->getHtml();

		$this->assertStringNotContainsString( '(fileimporter-category-encouragement)', $html );
		$this->assertStringContainsString( ' class="catlinks"', $html );
		$this->assertStringContainsString( ">$category</a>", $html );
	}

	// FIXME: This misses a test for hidden categories!

}
