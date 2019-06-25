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

		Theme::setSingleton( new BlankTheme() );
	}

	public function testGetHtml_uncategorized() {
		$categoriesSnippet = new CategoriesSnippet( [], [] );
		$html = $categoriesSnippet->getHtml();

		assertThat(
			$html,
			is( htmlPiece(
				havingChild(
					withClass( 'oo-ui-iconWidget' )
				)
			) )
		);

		// Without this line, PHPUnit doesn't count Hamcrest assertions and marks the test as risky.
		$this->addToAssertionCount( 1 );
	}

	public function testGetHtml_hasOneCategory() {
		$category = 'Puppies ' . mt_rand();
		$categoriesSnippet = new CategoriesSnippet( [ $category ], [] );
		$html = $categoriesSnippet->getHtml();
		assertThat(
			$html,
			is( htmlPiece(
				both( havingChild(
					withClass( 'catlinks' )
				) )
					->andAlso( havingChild(
						havingTextContents( $category )
					) )
					->andAlso( not( havingChild(
						withClass( 'oo-ui-iconWidget' )
					) ) )
			) )
		);

		// Without this line, PHPUnit doesn't count Hamcrest assertions and marks the test as risky.
		$this->addToAssertionCount( 1 );
	}

	// FIXME: This misses a test for hidden categories!

}
