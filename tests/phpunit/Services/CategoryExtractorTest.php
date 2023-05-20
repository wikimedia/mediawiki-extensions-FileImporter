<?php

namespace FileImporter\Tests\Services;

use FileImporter\Services\CategoryExtractor;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\WikiPageFactory;
use MediaWikiIntegrationTestCase;
use Parser;
use ParserOutput;
use Title;
use User;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\TestingAccessWrapper;

/**
 * @group Database
 *
 * @coversDefaultClass \FileImporter\Services\CategoryExtractor
 *
 * @license GPL-2.0-or-later
 */
class CategoryExtractorTest extends MediaWikiIntegrationTestCase {

	/** @var MediaWikiServices */
	private $services;
	/** @var WikiPageFactory */
	private $wikiPageFactory;

	public function setUp(): void {
		parent::setUp();

		$this->services = MediaWikiServices::getInstance();
		$this->wikiPageFactory = $this->services->getWikiPageFactory();
	}

	public static function provideCategories() {
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
	public function testGetCategories( array $allCategories, array $hiddenCategories, array $visibleCategories ) {
		$extractor = new CategoryExtractor(
			$this->buildParserMock( $allCategories ),
			$this->buildConnectionProviderMock( $hiddenCategories ),
			$this->services->getLinkBatchFactory()
		);

		$title = Title::makeTitle( NS_FILE, 'Foo' );
		[ $outVisibleCategories, $outHiddenCategories ] =
			$extractor->getCategoriesGrouped( '', $title, $this->createMock( User::class ) );

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
		$categoryPageVisible = $this->wikiPageFactory->newFromTitle( $categoryTitleVisible );
		$categoryPageVisible->insertOn( $this->db );

		$categoryTitleHidden = Title::makeTitle( NS_CATEGORY, 'CategoryPageHidden' );
		$categoryPageHidden = $this->wikiPageFactory->newFromTitle( $categoryTitleHidden );
		$categoryPageHidden->insertOn( $this->db );
		$this->setHiddencat( $categoryPageHidden->getId() );

		$categoryTitleHiddenUnused = Title::makeTitle( NS_CATEGORY, 'CategoryPageHiddenUnused' );
		$categoryPageHiddenUnused = $this->wikiPageFactory->newFromTitle( $categoryTitleHiddenUnused );
		$categoryPageHiddenUnused->insertOn( $this->db );
		$this->setHiddencat( $categoryPageHiddenUnused->getId() );

		$extractor = new CategoryExtractor(
			$this->createMock( Parser::class ),
			$this->services->getDBLoadBalancerFactory(),
			$this->services->getLinkBatchFactory()
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
		$categoryPageVisible = $this->wikiPageFactory->newFromTitle( $categoryTitleVisible );
		$categoryPageVisible->insertOn( $this->db );

		$categoryTitleHiddenUnused = Title::makeTitle( NS_CATEGORY, 'CategoryPageHiddenUnused' );
		$categoryPageHiddenUnused = $this->wikiPageFactory->newFromTitle( $categoryTitleHiddenUnused );
		$categoryPageHiddenUnused->insertOn( $this->db );
		$this->setHiddencat( $categoryPageHiddenUnused->getId() );

		$extractor = new CategoryExtractor(
			$this->createMock( Parser::class ),
			$this->services->getDBLoadBalancerFactory(),
			$this->services->getLinkBatchFactory()
		);
		$openExtractor = TestingAccessWrapper::newFromObject( $extractor );

		$fileCategories = [];
		$hiddenCategories = $openExtractor->queryHiddenCategories( $fileCategories );
		$this->assertEquals( [], array_values( $hiddenCategories ) );
	}

	private function buildParserMock( array $categories ): Parser {
		$parserOutput = $this->createMock( ParserOutput::class );
		$parserOutput->method( 'getCategories' )
			->willReturn( array_flip( $categories ) );

		$parser = $this->createMock( Parser::class );
		$parser->method( 'parse' )
			->willReturn( $parserOutput );

		return $parser;
	}

	private function buildConnectionProviderMock( array $hiddenCategories ): IConnectionProvider {
		$database = $this->createMock( IReadableDatabase::class );
		$database->method( 'selectFieldValues' )
			->willReturn( $hiddenCategories );

		$connectionProvider = $this->createMock( IConnectionProvider::class );
		$connectionProvider->method( 'getReplicaDatabase' )
			->willReturn( $database );

		return $connectionProvider;
	}

	private function setHiddencat( int $page_id ): void {
		$this->db->insert(
			'page_props',
			[
				'pp_page' => $page_id,
				'pp_propname' => 'hiddencat',
				'pp_value' => 1,
			],
			__METHOD__,
			[ 'IGNORE' ]
		);
	}

}
