<?php
declare( strict_types = 1 );

namespace FileImporter\Tests\Html;

use FileImporter\Html\CategoriesSnippet;
use MediaWiki\Language\MessageLocalizer;
use MediaWiki\Language\RawMessage;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWikiIntegrationTestCase;
use OOUI\BlankTheme;
use OOUI\Theme;

/**
 * @covers \FileImporter\Html\CategoriesSnippet
 *
 * @group Database
 * @license GPL-2.0-or-later
 */
class CategoriesSnippetTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();

		$this->setUserLang( 'qqx' );
		Theme::setSingleton( new BlankTheme() );
	}

	private function getMockSpecialPage(): SpecialPage {
		$messageLocalizer = $this->createMock( MessageLocalizer::class );
		$messageLocalizer->method( 'msg' )
			->willReturnCallback( static fn ( $key ) => new RawMessage( "($key)" ) );

		$mock = $this->createNoOpMock( SpecialPage::class, [ 'getContext' ] );
		$mock->method( 'getContext' )
			->willReturn( $messageLocalizer );
		return $mock;
	}

	public function testGetHtml_uncategorized() {
		$categoriesSnippet = new CategoriesSnippet( $this->getMockSpecialPage() );
		$html = $categoriesSnippet->getHtml( [], [] );

		$this->assertStringContainsString( '(fileimporter-category-encouragement)', $html );
	}

	public function testGetHtml_hasOneCategory() {
		$category = 'Puppies ' . mt_rand();
		$categoriesSnippet = new CategoriesSnippet( $this->getMockSpecialPage() );
		$html = $categoriesSnippet->getHtml( [ $category ], [] );

		$this->assertStringNotContainsString( '(fileimporter-category-encouragement)', $html );
		$this->assertStringContainsString( ' class="catlinks"', $html );
		$this->assertStringContainsString( ">$category</a>", $html );
	}

	// FIXME: This misses a test for hidden categories!

}
