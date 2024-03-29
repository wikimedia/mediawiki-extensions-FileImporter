<?php

namespace FileImporter\Tests\Remote\MediaWiki;

use FileImporter\Data\SourceUrl;
use FileImporter\Remote\MediaWiki\CommonsHelperConfigRetriever;
use FileImporter\Services\Http\HttpRequestExecutor;
use MediaWiki\MainConfigNames;
use MediaWiki\Revision\SlotRecord;

/**
 * @covers \FileImporter\Remote\MediaWiki\CommonsHelperConfigRetriever
 *
 * @license GPL-2.0-or-later
 * @author Thiemo Kreuz
 */
class CommonsHelperConfigRetrieverTest extends \MediaWikiIntegrationTestCase {

	public static function provideSourceUrls() {
		return [
			[ '//de.wikipedia.org/wiki/Example.svg', 'Data_de.wikipedia' ],
			[ '//en.MediaWiki.org', 'Data_en.mediawiki' ],

			[ '//www.mediawiki.org', 'Data_www.mediawiki' ],
			[ '//www.Wikipedia.org', 'Data_www.wikipedia' ],

			[ '//mediawiki.org', 'Data_www.mediawiki' ],
			[ '//wikipedia.org', 'Data_www.wikipedia' ],

			[ '//en.m.de', 'Data_en.m' ],
			[ '//en.comics.wikia.com', 'Data_en.comics.wikia' ],
		];
	}

	/**
	 * @dataProvider provideSourceUrls
	 */
	public function testSuccess( string $sourceUrl, string $configPage ) {
		$this->overrideConfigValues( [
			MainConfigNames::ArticlePath => '/wiki/$1',
			MainConfigNames::ScriptPath => '/w',
		] );

		$request = $this->createMWHttpRequest( [
				'query' => [
					'pages' => [
						[
							'revisions' => [
								[
									'slots' => [
										SlotRecord::MAIN => [ 'content' => '<WIKITEXT>' ],
									],
								],
							],
						],
					],
				],
			] );

		$requestExecutor = $this->createMock( HttpRequestExecutor::class );
		$requestExecutor->method( 'execute' )
			->with( '<SERVER>/w/api.php', [
				'action' => 'query',
				'format' => 'json',
				'titles' => $configPage,
				'prop' => 'revisions',
				'errorformat' => 'plaintext',
				'formatversion' => 2,
				'rvprop' => 'content',
				'rvlimit' => 1,
				'rvslots' => SlotRecord::MAIN,
				'rvdir' => 'older',
			] )
			->willReturn( $request );

		$retriever = new CommonsHelperConfigRetriever(
			$requestExecutor,
			'<SERVER>',
			'Data '
		);

		$this->assertTrue( $retriever->retrieveConfiguration( new SourceUrl( $sourceUrl ) ) );
		$this->assertSame( "<SERVER>/wiki/$configPage", $retriever->getConfigWikiUrl() );
		$this->assertSame( '<WIKITEXT>', $retriever->getConfigWikitext() );
	}

	public static function provideMissingResponses() {
		return [
			[ [], ],
			[ [ 'query' => [], ], ],
			[ [ 'query' => [ 'pages' => [], ], ], ],
			[ [ 'query' => [ 'pages' => [ [ 'missing' => 'missing' ], ], ], ], ]
		];
	}

	/**
	 * @dataProvider provideMissingResponses
	 */
	public function testRetrievalFails( array $queryResponse ) {
		$request = $this->createMWHttpRequest( $queryResponse );

		$requestExecutor = $this->createMock( HttpRequestExecutor::class );
		$requestExecutor->method( 'execute' )
			->willReturn( $request );

		$retriever = new CommonsHelperConfigRetriever(
			$requestExecutor,
			'',
			''
		);

		$this->assertFalse( $retriever->retrieveConfiguration( new SourceUrl( '//en.m.de' ) ) );
	}

	private function createMWHttpRequest( array $response ) {
		$request = $this->createMock( \MWHttpRequest::class );
		$request->method( 'getContent' )
			->willReturn( json_encode( $response ) );
		return $request;
	}

}
