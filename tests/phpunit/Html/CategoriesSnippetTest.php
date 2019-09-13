<?php

namespace FileImporter\Tests\Html;

use FileImporter\Html\CategoriesSnippet;
use MediaWikiTestCase;
use OOUI\BlankTheme;
use OOUI\Theme;

/**
 * @covers \FileImporter\Html\CategoriesSnippet
 */
class CategoriesSnippetTest extends MediaWikiTestCase {

	public function setUp() {
		parent::setUp();

		$this->setUserLang( 'qqx' );
		Theme::setSingleton( new BlankTheme() );
	}

	public function testGetHtml_uncategorized() {
		$categoriesSnippet = new CategoriesSnippet( [], [] );
		$html = $categoriesSnippet->getHtml();

		$this->assertContains( '(fileimporter-category-encouragement)', $html );
	}

	public function testGetHtml_hasOneCategory() {
		$category = 'Puppies ' . mt_rand();
		$categoriesSnippet = new CategoriesSnippet( [ $category ], [] );
		$html = $categoriesSnippet->getHtml();

		$this->assertNotContains( '(fileimporter-category-encouragement)', $html );
		$this->assertContains( ' class="catlinks"', $html );
		$this->assertContains( ">$category</a>", $html );
	}

	// FIXME: This misses a test for hidden categories!

}
