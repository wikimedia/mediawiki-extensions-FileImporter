<?php

namespace FileImporter\Tests\Remote\MediaWiki;

use ConfigException;
use FileImporter\Data\SourceUrl;
use FileImporter\Exceptions\LocalizedImportException;
use FileImporter\Remote\MediaWiki\ApiDetailRetriever;
use FileImporter\Remote\MediaWiki\HttpApiLookup;
use FileImporter\Services\Http\HttpRequestExecutor;
use MWHttpRequest;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \FileImporter\Remote\MediaWiki\ApiDetailRetriever
 * @covers \FileImporter\Remote\MediaWiki\MediaWikiSourceUrlParser
 *
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class ApiDetailRetrieverTest extends \MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();

		$this->setMwGlobals( [
			'wgFileImporterCommonsHelperServer' => '',
			'wgFileImporterMaxRevisions' => 4,
			'wgFileImporterMaxAggregatedBytes' => 9,
		] );
	}

	public function provideSourceUrls() {
		return [
			[ 'http://w.wiki', null ],
			[ 'http://w.wiki/', null ],
			[ 'http://w.wiki/A/', null ],
			[ 'http://w.wiki/0', '0' ],
			[ 'http://w.wiki/A', 'A' ],
			[ 'http://w.wiki//B', 'B' ],
			[ 'http://w.wiki/A/B', 'B' ],
			[ 'http://w.wiki/A?query#fragment', 'A' ],

			// title=… always has a higher priority, no matter what it contains.
			[ 'http://w.wiki/A?title', null ],
			[ 'http://w.wiki/A?title=', null ],
			[ 'http://w.wiki/A?title=B', 'B' ],

			// Yes, these different results match the behavior of MediaWiki core!
			[ 'http://w.wiki/B+C', 'B+C' ],
			[ 'http://w.wiki/A?title=B+C', 'B C' ],

			// Make sure %… sequences are not decoded twice.
			[ 'http://w.wiki/100%25%32%35', '100%25' ],
			[ 'http://w.wiki/A?title=100%25%32%35', '100%25' ],
		];
	}

	/**
	 * @dataProvider provideSourceUrls
	 */
	public function testSourceUrlParsing( $sourceUrl, $expected ) {
		$apiRetriever = $this->newInstance();
		$title = $apiRetriever->parseTitleFromSourceUrl( new SourceUrl( $sourceUrl ) );
		$this->assertSame( $expected, $title );
	}

	public function testInvalidSuppressedUser() {
		$this->setMwGlobals( [
			'wgFileImporterAccountForSuppressedUsername' => 'InValid#Name'
		] );
		$this->expectException( ConfigException::class );

		$this->newInstance();
	}

	public function testCheckMaxRevisionAggregatedBytes_setMax() {
		$apiRetriever = $this->newInstance();

		$this->expectException( LocalizedImportException::class );
		$apiRetriever->checkMaxRevisionAggregatedBytes( [ 'imageinfo' => [ [ 'size' => 1000 ] ] ] );
	}

	public function provideTestCheckMaxRevisionAggregatedBytes_passes() {
		return [
			'one byte' => [
				[ 'imageinfo' => [ [ 'size' => 1 ] ] ]
			],
			'multiple revisions, exactly at the limit' => [
				[
					'imageinfo' => [
						[ 'size' => 8 ],
						[ 'size' => 1 ],
					]
				]
			],
			'one revision, exactly at the limit' => [
				[ 'imageinfo' => [ [ 'size' => 9 ] ] ]
			],
		];
	}

	/**
	 * @dataProvider provideTestCheckMaxRevisionAggregatedBytes_passes
	 */
	public function testCheckMaxRevisionAggregatedBytes_passes( array $input ) {
		$apiRetriever = $this->newInstance();

		$apiRetriever->checkMaxRevisionAggregatedBytes( $input );

		$this->addToAssertionCount( 1 );
	}

	public function provideTestCheckMaxRevisionAggregatedBytes_fails() {
		return [
			'small sizes, to large when added' => [
				[
					'imageinfo' => [
						[ 'size' => 8 ],
						[ 'size' => 2 ],
					]
				]
			],
			'one large revision' => [
				[ 'imageinfo' => [ [ 'size' => 10 ] ] ]
			],
		];
	}

	/**
	 * @dataProvider provideTestCheckMaxRevisionAggregatedBytes_fails
	 */
	public function testCheckMaxRevisionAggregatedBytes_fails( array $input ) {
		$apiRetriever = $this->newInstance();

		$this->expectException( LocalizedImportException::class );

		$apiRetriever->checkMaxRevisionAggregatedBytes( $input );
	}

	public function provideCheckRevisionCount_fails() {
		return [
			'to many image revisions' => [
				[
					'imageinfo' => array_fill( 0, 5, null ),
					'revisions' => array_fill( 0, 4, null ),
				]
			],
			'to many text revisions' => [
				[
					'imageinfo' => array_fill( 0, 4, null ),
					'revisions' => array_fill( 0, 5, null ),
				]
			]
		];
	}

	/**
	 * @dataProvider provideCheckRevisionCount_fails
	 */
	public function testCheckRevisionCount_fails( array $input ) {
		$apiRetriever = $this->newInstance();

		$this->expectException( LocalizedImportException::class );

		$apiRetriever->checkRevisionCount(
			$this->createMock( SourceUrl::class ),
			$input
		);
	}

	public function provideCheckRevisionCount_passes() {
		return [
			'no revisions' => [
				[
					'imageinfo' => [],
					'revisions' => [],
				]
			],
			'maximum number of revisions' => [
				[
					'imageinfo' => array_fill( 0, 4, null ),
					'revisions' => array_fill( 0, 4, null ),
				]
			]
		];
	}

	/**
	 * @dataProvider provideCheckRevisionCount_passes
	 */
	public function testCheckRevisionCount_passes( array $input ) {
		$apiRetriever = $this->newInstance();

		$apiRetriever->checkRevisionCount(
			$this->createMock( SourceUrl::class ),
			$input
		);

		$this->addToAssertionCount( 1 );
	}

	public function provideGetMoreRevisions_passes() {
		return [
			'1st request continues' => [
				'existingData' => [
					'sourceUrl' => new SourceUrl( '//en.wikipedia.org/wiki/F%C3%B6o' ),
					'requestData' => [
						'continue' => []
					],
					'pageInfoData' => [
						'imageinfo' => [],
						'revisions' => []
					],
				],
				'apiResponse' => [
					'query' => [
						'pages' => [
							[
								'imageinfo' => [
									[ 'size' => 1 ],
								],
								'revisions' => [
									[ 'comment' => 'textRev1' ],
								]
							]
						]
					],
					'continue' => 'CONTINUE'
				],
				'expected' => [
					'apiParameters' => [
						'action' => 'query',
						'format' => 'json',
						'titles' => 'Föo',
						'prop' => 'info',
					],
					'data' => [
						'imageinfo' => [
							[ 'size' => 1 ],
						],
						'revisions' => [
							[ 'comment' => 'textRev1' ],
						]
					],
					'continue' => 'CONTINUE'
				]
			],

			'2nd request does not continue' => [
				'existingData' => [
					'sourceUrl' => new SourceUrl( '//en.wikipedia.org/wiki/File:Foo.jpg' ),
					'requestData' => [
						'continue' => [
							'rvcontinue' => 'rvContinueHere',
							'iistart' => 'iiStartHere',
							'continue' => 'revisions||imageinfo'
						]
					],
					'pageInfoData' => [
						'imageinfo' => [
							[ 'size' => 0 ],
							[ 'size' => 1 ]
						],
						'revisions' => [
							[ 'comment' => 'textRev1' ],
							[ 'comment' => 'textRev2' ]
						]
					]
				],
				'apiResponse' => [
					'query' => [
						'pages' => [
							[
								'imageinfo' => [
									[ 'size' => 2 ],
									[ 'size' => 3 ]
								],
								'revisions' => [
									[ 'comment' => 'textRev3' ],
									[ 'comment' => 'textRev4' ]
								]
							]
						]
					]
				],
				'expected' => [
					'apiParameters' => [
						'action' => 'query',
						'format' => 'json',
						'titles' => 'File:Foo.jpg',
						'prop' => 'info|imageinfo|revisions',
						'iistart' => 'iiStartHere',
						'iilimit' => 500,
						'iiurlwidth' => 800,
						'iiurlheight' => 400,
						'iiprop' => 'timestamp|user|userid|comment|canonicaltitle|url|size|sha1|archivename',
						'rvcontinue' => 'rvContinueHere',
						'rvlimit' => 500,
						'rvdir' => 'newer',
						'rvprop' => 'flags|timestamp|user|sha1|contentmodel|comment|content|tags',
					],
					'data' => [
						'imageinfo' => [
							[ 'size' => 0 ],
							[ 'size' => 1 ],
							[ 'size' => 2 ],
							[ 'size' => 3 ]
						],
						'revisions' => [
							[ 'comment' => 'textRev1' ],
							[ 'comment' => 'textRev2' ],
							[ 'comment' => 'textRev3' ],
							[ 'comment' => 'textRev4' ]
						]
					],
					'continue' => false
				]
			]
		];
	}

	/**
	 * @dataProvider provideGetMoreRevisions_passes
	 */
	public function testGetMoreRevisions( array $existingData, array $apiResponse, array $expected ) {
		$apiRetriever = new ApiDetailRetriever(
			$this->getMockHttpApiLookup(),
			$this->getMockHttpRequestExecutorWithExpectedRequest(
				$expected[ 'apiParameters' ],
				json_encode( $apiResponse )
			),
			0
		);

		/** @var ApiDetailRetriever $apiRetriever */
		$apiRetriever = TestingAccessWrapper::newFromObject( $apiRetriever );

		call_user_func_array(
			[ $apiRetriever, 'getMoreRevisions' ],
			[
				$existingData['sourceUrl'],
				&$existingData['requestData'],
				&$existingData['pageInfoData']
			]
		);

		if ( $expected['continue'] ) {
			$this->assertSame( $expected['continue'], $existingData['requestData']['continue'] );
		} else {
			$this->assertArrayNotHasKey( 'continue', $existingData['requestData'] );
		}
		$this->assertSame( $expected['data'], $existingData['pageInfoData'] );
	}

	public function provideTestInvalidResponse() {
		return [
			[
				[ 'query' => [ 'pages' => [] ] ],
				new LocalizedImportException( 'fileimporter-api-nopagesreturned' ),
			],
			[
				[ 'query' => [ 'pages' => [ [ 'missing' => '' ] ] ] ],
				new LocalizedImportException( 'fileimporter-cantimportmissingfile' ),
			],
			[
				[ 'query' => [ 'pages' => [ [ 'missing' => '', 'imagerepository' => 'shared' ] ] ] ],
				new LocalizedImportException( [ 'fileimporter-cantimportfromsharedrepo', 'foo.bar' ] ),
			],
			[
				[ 'query' => [ 'pages' => [ [ 'title' => 'Test' ] ] ] ],
				new LocalizedImportException( 'fileimporter-api-badinfo' ),
			],
		];
	}

	/**
	 * @dataProvider provideTestInvalidResponse
	 */
	public function testInvalidResponse( array $content, LocalizedImportException $expected ) {
		$service = new ApiDetailRetriever(
			$this->getMockHttpApiLookup(),
			$this->getMockHttpRequestExecutor( 'File:Foo.jpg', json_encode( $content ) ),
			0
		);

		$this->expectException( get_class( $expected ) );
		$this->expectExceptionMessage( $expected->getMessage() );

		$service->getImportDetails( new SourceUrl( 'http://foo.bar/wiki/File:Foo.jpg' ) );
	}

	public function provideTestValidResponse() {
		return [
			[
				new SourceUrl( 'http://en.wikipedia.org/wiki/File:Foo.png' ),
				'File:Foo.png',
				json_encode( $this->getFullRequestContent( 'File:Foo.png' ) ),
				[
					'titlestring' => 'Foo.png',
					'filename' => 'Foo',
					'ext' => 'png',
				],
			],
			[
				new SourceUrl( 'http://de.wikipedia.org/wiki/Datei:Bar+%31.JPG' ),
				'Datei:Bar+1.JPG',
				json_encode( $this->getFullRequestContent( 'Datei:Bar+1.JPG' ) ),
				[
					'titlestring' => 'Bar+1.JPG',
					'filename' => 'Bar+1',
					'ext' => 'JPG',
				],
			],
		];
	}

	/**
	 * @param string $titleString
	 *
	 * @return array[]
	 */
	private function getFullRequestContent( $titleString ) {
		return [
			'query' => [
				'pages' => [
					[
						'title' => $titleString,
						'imageinfo' => [
							[
								'name' => 'name',
								'description' => 'description',
								'user' => 'user',
								'timestamp' => '201701010202',
								'sha1' => 'sha1-image',
								'thumburl' => 'thumburl',
								'url' => 'url',
								'size' => 0,
								'comment' => 'comment',
							],
						],
						'revisions' => [
							[
								'minor' => 'minor',
								'user' => 'user',
								'timestamp' => '201701010202',
								'sha1' => 'sha1-rev',
								'contentmodel' => 'contentmodel',
								'contentformat' => 'contentformat',
								'comment' => 'comment',
								'*' => '*',
								'title' => $titleString,
								'tags' => [],
							],
						],
					]
				],
			],
		];
	}

	/**
	 * @dataProvider provideTestValidResponse
	 */
	public function testValidResponse(
		SourceUrl $sourceUrl,
		$titleString,
		$content,
		array $expected
	) {
		$service = new ApiDetailRetriever(
			$this->getMockHttpApiLookup(),
			$this->getMockHttpRequestExecutor( $titleString, $content ),
			0
		);
		$importDetails = $service->getImportDetails( $sourceUrl );

		$this->assertEquals( $sourceUrl, $importDetails->getSourceUrl() );
		$this->assertSame( 'thumburl', $importDetails->getImageDisplayUrl() );
		$this->assertSame( $expected['ext'], $importDetails->getSourceFileExtension() );
		$this->assertSame( $expected['filename'], $importDetails->getSourceFileName() );

		$this->assertSame( $expected['titlestring'], $importDetails->getSourceLinkTarget()->getText() );
		$this->assertSame( NS_FILE, $importDetails->getSourceLinkTarget()->getNamespace() );
	}

	/**
	 * @return ApiDetailRetriever
	 */
	private function newInstance() {
		$apiDetailRetriever = new ApiDetailRetriever(
			$this->getMockHttpApiLookup(),
			$this->createMock( HttpRequestExecutor::class ),
			0
		);
		return TestingAccessWrapper::newFromObject( $apiDetailRetriever );
	}

	private function getMockHttpApiLookup(): HttpApiLookup {
		$mock = $this->createMock( HttpApiLookup::class );
		$mock->method( 'getApiUrl' )
			->willReturn( 'APIURL' );
		return $mock;
	}

	/**
	 * @param string $titleString
	 * @param string $content
	 *
	 * @return HttpRequestExecutor
	 */
	private function getMockHttpRequestExecutor( $titleString, $content ): HttpRequestExecutor {
		return $this->getMockHttpRequestExecutorWithExpectedRequest(
			$this->getExpectedApiParameters( $titleString ),
			$content
		);
	}

	/**
	 * @param array $expectedApiParameters
	 * @param string $content
	 *
	 * @return HttpRequestExecutor
	 */
	private function getMockHttpRequestExecutorWithExpectedRequest(
		array $expectedApiParameters,
		$content
	): HttpRequestExecutor {
		$mock = $this->createMock( HttpRequestExecutor::class );
		$mock->expects( $this->once() )
			->method( 'execute' )
			->with( 'APIURL', $expectedApiParameters )
			->willReturn( $this->getMockMWHttpRequest( $content ) );
		return $mock;
	}

	/**
	 * @param string $titleString
	 *
	 * @return array
	 */
	private function getExpectedApiParameters( $titleString ) {
		return [
			'action' => 'query',
			'format' => 'json',
			'titles' => $titleString,
			'prop' => 'info|imageinfo|revisions|templates|categories',
			'iilimit' => 500,
			'iiurlwidth' => 800,
			'iiurlheight' => 400,
			'iiprop' => 'timestamp|user|userid|comment|canonicaltitle|url|size|sha1|archivename',
			'rvlimit' => 500,
			'rvdir' => 'newer',
			'rvprop' => 'flags|timestamp|user|sha1|contentmodel|comment|content|tags',
			'tlnamespace' => NS_TEMPLATE,
			'tllimit' => 500,
			'cllimit' => 500,
		];
	}

	/**
	 * @param string $content
	 *
	 * @return MWHttpRequest
	 */
	private function getMockMWHttpRequest( $content ): MWHttpRequest {
		$mock = $this->createMock( MWHttpRequest::class );
		$mock->method( 'getContent' )
			->willReturn( $content );
		return $mock;
	}

}
