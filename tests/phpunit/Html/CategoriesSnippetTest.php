<?php

namespace FileImporter\Html\Test;

use FileImporter\Html\CategoriesSnippet;
use MediaWikiTestCase;

/**
 * @covers \FileImporter\Html\CategoriesSnippet
 */
class CategoriesSnippetTest extends MediaWikiTestCase {

	public function testGetHtml_uncategorized() {
		$categoriesSnippet = new CategoriesSnippet( [], [] );
		$html = $categoriesSnippet->getHtml();

		$this->assertEquals( '', $html );
	}

	public function testGetHtml_hasOneCategory() {
		$category = 'Puppies ' . mt_rand();
		$categoriesSnippet = new CategoriesSnippet( [ $category ], [] );
		$html = $categoriesSnippet->getHtml();
		assertThat(
			$html,
			is( htmlPiece(
				both( havingChild(
					withAttribute( 'class' )
						->havingValue( 'catlinks' )
				) )
					->andAlso( havingChild(
						havingTextContents( $category )
					) )
			) )
		);

		// Without this line, PHPUnit doesn't count Hamcrest assertions and marks the test as risky.
		$this->addToAssertionCount( 1 );
	}

	// FIXME: This misses a test for hidden categories!

}
