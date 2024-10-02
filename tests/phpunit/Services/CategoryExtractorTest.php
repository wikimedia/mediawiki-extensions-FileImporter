<?php

namespace FileImporter\Tests\Services;

use FileImporter\Services\CategoryExtractor;
use MediaWiki\Cache\LinkBatchFactory;
use MediaWiki\Parser\Parser;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use MediaWikiIntegrationTestCase;
use ParserFactory;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\Rdbms\SelectQueryBuilder;
use Wikimedia\TestingAccessWrapper;

/**
 * @group Database
 *
 * @coversDefaultClass \FileImporter\Services\CategoryExtractor
 *
 * @license GPL-2.0-or-later
 */
class CategoryExtractorTest extends MediaWikiIntegrationTestCase {

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
			$this->buildParserFactoryMock( $allCategories ),
			$this->buildConnectionProviderMock( $hiddenCategories ),
			$this->createMock( LinkBatchFactory::class )
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
		$services = $this->getServiceContainer();
		$wikiPageFactory = $services->getWikiPageFactory();

		$categoryTitleVisible = Title::makeTitle( NS_CATEGORY, 'CategoryPageVisible' );
		$categoryPageVisible = $wikiPageFactory->newFromTitle( $categoryTitleVisible );
		$categoryPageVisible->insertOn( $this->db );

		$categoryTitleHidden = Title::makeTitle( NS_CATEGORY, 'CategoryPageHidden' );
		$categoryPageHidden = $wikiPageFactory->newFromTitle( $categoryTitleHidden );
		$categoryPageHidden->insertOn( $this->db );
		$this->setHiddencat( $categoryPageHidden->getId() );

		$categoryTitleHiddenUnused = Title::makeTitle( NS_CATEGORY, 'CategoryPageHiddenUnused' );
		$categoryPageHiddenUnused = $wikiPageFactory->newFromTitle( $categoryTitleHiddenUnused );
		$categoryPageHiddenUnused->insertOn( $this->db );
		$this->setHiddencat( $categoryPageHiddenUnused->getId() );

		$extractor = new CategoryExtractor(
			$this->createNoOpMock( ParserFactory::class ),
			$services->getConnectionProvider(),
			$services->getLinkBatchFactory()
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
		$wikiPageFactory = $this->getServiceContainer()->getWikiPageFactory();

		$categoryTitleVisible = Title::makeTitle( NS_CATEGORY, 'CategoryPageVisible' );
		$categoryPageVisible = $wikiPageFactory->newFromTitle( $categoryTitleVisible );
		$categoryPageVisible->insertOn( $this->db );

		$categoryTitleHiddenUnused = Title::makeTitle( NS_CATEGORY, 'CategoryPageHiddenUnused' );
		$categoryPageHiddenUnused = $wikiPageFactory->newFromTitle( $categoryTitleHiddenUnused );
		$categoryPageHiddenUnused->insertOn( $this->db );
		$this->setHiddencat( $categoryPageHiddenUnused->getId() );

		$extractor = new CategoryExtractor(
			$this->createNoOpMock( ParserFactory::class ),
			$this->createNoOpMock( IConnectionProvider::class ),
			$this->createNoOpMock( LinkBatchFactory::class )
		);
		$openExtractor = TestingAccessWrapper::newFromObject( $extractor );

		$fileCategories = [];
		$hiddenCategories = $openExtractor->queryHiddenCategories( $fileCategories );
		$this->assertEquals( [], array_values( $hiddenCategories ) );
	}

	private function buildParserFactoryMock( array $categories ): ParserFactory {
		$parserOutput = $this->createMock( ParserOutput::class );
		$parserOutput->method( 'getCategoryNames' )
			->willReturn( $categories );

		$parser = $this->createMock( Parser::class );
		$parser->method( 'parse' )
			->willReturn( $parserOutput );

		$parserFactory = $this->createMock( ParserFactory::class );
		$parserFactory->method( 'getInstance' )
			->willReturn( $parser );

		return $parserFactory;
	}

	private function buildConnectionProviderMock( array $hiddenCategories ): IConnectionProvider {
		$queryBuilder = $this->createMock( SelectQueryBuilder::class );
		$queryBuilder->method( $this->logicalOr( 'select', 'from', 'join', 'where', 'caller' ) )->willReturnSelf();
		$queryBuilder->method( 'fetchFieldValues' )
			->willReturn( $hiddenCategories );

		$database = $this->createMock( IReadableDatabase::class );
		$database->method( 'newSelectQueryBuilder' )
			->willReturn( $queryBuilder );

		$connectionProvider = $this->createMock( IConnectionProvider::class );
		$connectionProvider->method( 'getReplicaDatabase' )
			->willReturn( $database );

		return $connectionProvider;
	}

	private function setHiddencat( int $page_id ): void {
		$this->getDb()->newInsertQueryBuilder()
			->insertInto( 'page_props' )
			->ignore()
			->row( [
				'pp_page' => $page_id,
				'pp_propname' => 'hiddencat',
				'pp_value' => 1,
			] )
			->caller( __METHOD__ )
			->execute();
	}

}
