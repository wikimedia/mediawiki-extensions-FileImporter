<?php

namespace FileImporter\Remote\MediaWiki\Test;

use FileImporter\Data\SourceUrl;
use FileImporter\Exceptions\LocalizedImportException;
use FileImporter\Remote\MediaWiki\ApiDetailRetriever;
use FileImporter\Remote\MediaWiki\HttpApiLookup;
use FileImporter\Services\Http\HttpRequestExecutor;
use MediaWikiTestCase;
use MWHttpRequest;
use Exception;
use Psr\Log\NullLogger;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \FileImporter\Remote\MediaWiki\ApiDetailRetriever
 *
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class ApiDetailRetrieverTest extends MediaWikiTestCase {

	public function testCheckMaxRevisionAggregatedBytes_setMax() {
		$this->setMwGlobals( [ 'wgFileImporterMaxAggregatedBytes' => 9 ] );

		$apiRetriever = $this->newInstance();

		$this->setExpectedException( get_class(
			new LocalizedImportException( 'fileimporter-filetoolarge' ) )
		);
		$apiRetriever->checkMaxRevisionAggregatedBytes( [ 'imageinfo' => [ [ 'size' => 1000 ] ] ] );
	}

	public function provideTestCheckMaxRevisionAggregatedBytes_passes() {
		return [
			[
				[ 'imageinfo' => [ [ 'size' => 1 ] ] ]
			],
			[
				[
					'imageinfo' => [
						[
							'size' => 249999999
						],
						[
							'size' => 1
						]
					]
				]
			],
			[
				[ 'imageinfo' => [ [ 'size' => 250000000 ] ] ]
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
			[
				[
					'imageinfo' => [
						[
							'size' => 249999999
						],
						[
							'size' => 2
						]
					]
				]
			],
			[
				[ 'imageinfo' => [ [ 'size' => 250000001 ] ] ]
			],
		];
	}

	/**
	 * @dataProvider provideTestCheckMaxRevisionAggregatedBytes_fails
	 */
	public function testCheckMaxRevisionAggregatedBytes_fails( array $input ) {
		$apiRetriever = $this->newInstance();

		$this->setExpectedException( get_class(
			new LocalizedImportException( 'fileimporter-filetoolarge' ) )
		);

		$apiRetriever->checkMaxRevisionAggregatedBytes( $input );
	}

	public function provideCheckRevisionCount_fails() {
		return [
			[
				[
					'imageinfo' => array_fill( 0, 110, null ),
					'revisions' => array_fill( 0, 100, null )
				]
			],
			[
				[
					'imageinfo' => array_fill( 0, 105, null ),
					'revisions' => array_fill( 0, 105, null )
				]
			]
		];
	}

	/**
	 * @dataProvider provideCheckRevisionCount_fails
	 */
	public function testCheckRevisionCount_fails( array $input ) {
		$apiRetriever = $this->newInstance();

		$this->setExpectedException( get_class(
				new LocalizedImportException( 'fileimporter-api-toomanyrevisions' ) )
		);

		$apiRetriever->checkRevisionCount(
			$this->getMock( SourceUrl::class, [], [], '', false ),
			"",
			$input
		);
	}

	public function provideCheckRevisionCount_passes() {
		return [
			[
				[
					'imageinfo' => array_fill( 0, 100, null ),
					'revisions' => array_fill( 0, 100, null )
				]
			],
			[
				[
					'imageinfo' => array_fill( 0, 95, null ),
					'revisions' => array_fill( 0, 10, null )
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
			$this->getMock( SourceUrl::class, [], [], '', false ),
			"",
			$input
		);

		$this->addToAssertionCount( 1 );
	}

	public function provideGetMoreRevisions_passes() {
		return [
			[
				'existingData' => [
					'sourceUrl' => new SourceUrl( 'http://en.wikipedia.org/wiki/File:Foo.jpg' ),
					'apiUrl' => 'APIURL',
					'requestData' => [
						'continue' => [
							'rvcontinue' => 'rvContinueHere',
							'iistart' => 'iiStartHere',
							'continue' => 'revisions||imageinfo'
						]
					],
					'pageInfoData' => [
						'imageinfo' => [
							[ 'comment' => 'imageRev1', 'size' => '0' ],
							[ 'comment' => 'imageRev2', 'size' => '0' ]
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
									[ 'comment' => 'imageRev3', 'size' => '0' ],
									[ 'comment' => 'imageRev4', 'size' => '0' ]
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
					'url' => 'APIURL?action=query&format=json&titles=File%3AFoo.jpg' .
						'&prop=imageinfo%7Crevisions&iistart=iiStartHere&iilimit=500&iiurlwidth=800&iiurlheight=400' .
						'&iiprop=timestamp%7Cuser%7Cuserid%7Ccomment%7Ccanonicaltitle%7Curl%7Csize%7Csha1' .
						'&rvcontinue=rvContinueHere&rvlimit=500&rvdir=newer&rvprop=flags' .
						'%7Ctimestamp%7Cuser%7Csha1%7Ccontentmodel%7Ccomment%7Ccontent',
					'data' => [
						'imageinfo' => [
							[ 'comment' => 'imageRev1', 'size' => '0' ],
							[ 'comment' => 'imageRev2', 'size' => '0' ],
							[ 'comment' => 'imageRev3', 'size' => '0' ],
							[ 'comment' => 'imageRev4', 'size' => '0' ]
						],
						'revisions' => [
							[ 'comment' => 'textRev1' ],
							[ 'comment' => 'textRev2' ],
							[ 'comment' => 'textRev3' ],
							[ 'comment' => 'textRev4' ]
						]
					]
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
				$expected[ 'url' ], json_encode( $apiResponse )
			),
			new NullLogger()
		);

		$apiRetriever = TestingAccessWrapper::newFromObject( $apiRetriever );

		call_user_func_array(
			[ $apiRetriever, 'getMoreRevisions' ],
			[
				$existingData[ 'sourceUrl' ],
				$existingData[ 'apiUrl' ],
				&$existingData[ 'requestData' ],
				&$existingData[ 'pageInfoData' ]
			]
		);

		$this->assertArrayEquals( $existingData[ 'pageInfoData' ], $expected[ 'data' ] );
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
				new LocalizedImportException( 'fileimporter-cantimportfromsharedrepo' ),
			],
		];
	}

	/**
	 * @dataProvider provideTestInvalidResponse
	 */
	public function testInvalidResponse( array $content, Exception $expected ) {
		$service = new ApiDetailRetriever(
			$this->getMockHttpApiLookup(),
			$this->getMockHttpRequestExecutor( 'File:Foo.jpg', json_encode( $content ) ),
			new NullLogger()
		);

		$this->setExpectedException( get_class( $expected ), $expected->getMessage() );

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
				new SourceUrl( 'http://de.wikipedia.org/wiki/Datei:Bar.JPG' ),
				'Datei:Bar.JPG',
				json_encode( $this->getFullRequestContent( 'Datei:Bar.JPG' ) ),
				[
					'titlestring' => 'Bar.JPG',
					'filename' => 'Bar',
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
								'size' => 'size',
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
			new NullLogger()
		);
		$importDetails = $service->getImportDetails( $sourceUrl );

		$this->assertEquals( $sourceUrl, $importDetails->getSourceUrl() );
		$this->assertEquals( 'thumburl', $importDetails->getImageDisplayUrl() );
		$this->assertEquals( $expected['ext'], $importDetails->getSourceFileExtension() );
		$this->assertEquals( $expected['filename'], $importDetails->getSourceFileName() );

		$this->assertEquals( $expected['titlestring'], $importDetails->getSourceLinkTarget()->getText() );
		$this->assertEquals( NS_FILE, $importDetails->getSourceLinkTarget()->getNamespace() );
	}

	/**
	 * @return ApiDetailRetriever
	 */
	private function newInstance() {
		return TestingAccessWrapper::newFromObject( new ApiDetailRetriever(
			$this->getMockHttpApiLookup(),
			$this->getMock( HttpRequestExecutor::class, [], [], '', false ),
			new NullLogger()
		) );
	}

	/**
	 * @return HttpApiLookup
	 */
	private function getMockHttpApiLookup() {
		$mock = $this->getMock( HttpApiLookup::class, [], [], '', false );
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
	private function getMockHttpRequestExecutor( $titleString, $content ) {
		return $this->getMockHttpRequestExecutorWithExpectedRequest(
			$this->getExpectedExecuteRequestUrl( $titleString ),
			$content
		);
	}

	/**
	 * @param string $expectedRequestString
	 * @param string $content
	 *
	 * @return HttpRequestExecutor
	 */
	private function getMockHttpRequestExecutorWithExpectedRequest(
		$expectedRequestString,
		$content
	) {
		$mock = $this->getMock( HttpRequestExecutor::class, [], [], '', false );
		$mock->expects( $this->once() )
			->method( 'execute' )
			->with( $expectedRequestString )
			->willReturn( $this->getMockMWHttpRequest( $content ) );
		return $mock;
	}

	/**
	 * @param string $titleString
	 *
	 * @return string
	 */
	private function getExpectedExecuteRequestUrl( $titleString ) {
		return 'APIURL?action=query&format=json&titles=' . urlencode( $titleString ) .
				'&prop=imageinfo%7Crevisions&iilimit=500&iiurlwidth=800&iiurlheight=400'.
				'&iiprop=timestamp%7Cuser%7Cuserid%7Ccomment%7Ccanonicaltitle%7Curl%7Csize%7Csha1' .
				'&rvlimit=500&rvdir=newer&rvprop=flags%7Ctimestamp%7Cuser%7Csha1%7Ccontentmodel%7' .
				'Ccomment%7Ccontent';
	}

	/**
	 * @param string $content
	 *
	 * @return MWHttpRequest
	 */
	private function getMockMWHttpRequest( $content ) {
		$mock = $this->getMock( MWHttpRequest::class, [], [], '', false );
		$mock->method( 'getContent' )
			->willReturn( $content );
		return $mock;
	}

}
