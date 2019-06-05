<?php

namespace FileImporter\Tests\Services;

use FileImporter\Services\CategoryExtractor;
use MediaWiki\MediaWikiServices;
use MediaWikiTestCase;
use Parser;
use ParserOutput;
use Title;
use WikiCategoryPage;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\LoadBalancer;
use Wikimedia\TestingAccessWrapper;

/**
 * @group Database
 *
 * @coversDefaultClass \FileImporter\Services\CategoryExtractor
 */
class CategoryExtractorTest extends MediaWikiTestCase {

	public function provideCategories() {
		yield [
			[], [], []
		];
		yield [
			[ 'abc' ], [], [ 'abc' ]
		];
		yield [
			[ 'abc' ], [ 'abc' ], []
		];
		yield [
			[ 'abc', 'def' ], [], [ 'abc', 'def' ]
		];
		yield [
			[ 'abc', 'def' ], [ 'abc' ], [ 'def' ]
		];
		yield [
			[ 'abc', 'def' ], [ 'abc', 'def' ], []
		];
	}

	/**
	 * @covers ::__construct
	 * @covers ::getCategoriesGrouped
	 * @dataProvider provideCategories
	 */
	public function testGetCategories( $allCategories, $hiddenCategories, $visibleCategories ) {
		$extractor = new CategoryExtractor(
			$this->buildParserMock( $allCategories ),
			$this->buildLoadBalancerMock( $hiddenCategories )
		);

		$title = Title::makeTitle( NS_FILE, 'Foo' );
		list( $outVisibleCategories, $outHiddenCategories ) =
			$extractor->getCategoriesGrouped( '', $title );

		$this->assertEquals( $visibleCategories, array_values( $outVisibleCategories ) );
		$this->assertEquals( $hiddenCategories, array_values( $outHiddenCategories ) );
	}

	/**
	 * @covers ::__construct
	 * @covers ::queryHiddenCategories
	 */
	public function testQueryHiddenCategories() {
		$this->tablesUsed += [
			'page',
			'page_props',
		];

		$categoryTitleVisible = Title::makeTitle( NS_CATEGORY, 'CategoryPageVisible' );
		$categoryPageVisible = WikiCategoryPage::factory( $categoryTitleVisible );
		$categoryPageVisible->insertOn( $this->db );

		$categoryTitleHidden = Title::makeTitle( NS_CATEGORY, 'CategoryPageHidden' );
		$categoryPageHidden = WikiCategoryPage::factory( $categoryTitleHidden );
		$categoryPageHidden->insertOn( $this->db );
		$this->setHiddencat( $categoryPageHidden->getId() );

		$categoryTitleHiddenUnused = Title::makeTitle( NS_CATEGORY, 'CategoryPageHiddenUnused' );
		$categoryPageHiddenUnused = WikiCategoryPage::factory( $categoryTitleHiddenUnused );
		$categoryPageHiddenUnused->insertOn( $this->db );
		$this->setHiddencat( $categoryPageHiddenUnused->getId() );

		$extractor = new CategoryExtractor(
			$this->createMock( Parser::class ),
			MediaWikiServices::getInstance()->getDBLoadBalancer()
		);
		$openExtractor = TestingAccessWrapper::newFromObject( $extractor );

		$fileCategories = [ 'CategoryPageVisible', 'CategoryPageHidden' ];
		$expectedHiddenCategories = [ 'CategoryPageHidden' ];
		$hiddenCategories = $openExtractor->queryHiddenCategories( $fileCategories );
		$this->assertEquals( $expectedHiddenCategories, array_values( $hiddenCategories ) );
	}

	/**
	 * @covers ::queryHiddenCategories
	 */
	public function testQueryHiddenCategories_empty() {
		$this->tablesUsed += [
			'page',
			'page_props',
		];

		$categoryTitleVisible = Title::makeTitle( NS_CATEGORY, 'CategoryPageVisible' );
		$categoryPageVisible = WikiCategoryPage::factory( $categoryTitleVisible );
		$categoryPageVisible->insertOn( $this->db );

		$categoryTitleHiddenUnused = Title::makeTitle( NS_CATEGORY, 'CategoryPageHiddenUnused' );
		$categoryPageHiddenUnused = WikiCategoryPage::factory( $categoryTitleHiddenUnused );
		$categoryPageHiddenUnused->insertOn( $this->db );
		$this->setHiddencat( $categoryPageHiddenUnused->getId() );

		$extractor = new CategoryExtractor(
			$this->createMock( Parser::class ),
			MediaWikiServices::getInstance()->getDBLoadBalancer()
		);
		$openExtractor = TestingAccessWrapper::newFromObject( $extractor );

		$fileCategories = [];
		$hiddenCategories = $openExtractor->queryHiddenCategories( $fileCategories );
		$this->assertEquals( [], array_values( $hiddenCategories ) );
	}

	private function buildParserMock( array $categories ) {
		$parserOutput = $this->createMock( ParserOutput::class );
		$parserOutput->method( 'getCategories' )
			->willReturn( array_flip( $categories ) );

		$parser = $this->createMock( Parser::class );
		$parser->method( 'parse' )
			->willReturn( $parserOutput );

		return $parser;
	}

	private function buildLoadBalancerMock( $hiddenCategories ) {
		$database = $this->createMock( IDatabase::class );
		$database->method( 'selectFieldValues' )
			->willReturn( $hiddenCategories );

		$loadBalancer = $this->createMock( LoadBalancer::class );
		$loadBalancer->method( 'getConnection' )
			->willReturn( $database );

		return $loadBalancer;
	}

	private function setHiddencat( $page_id ) {
		$this->db->replace(
			'page_props',
			[ 'pp_page' => $page_id ],
			[ [
				'pp_page' => $page_id,
				'pp_propname' => 'hiddencat',
				'pp_value' => true,
			] ]
		);
	}

}
