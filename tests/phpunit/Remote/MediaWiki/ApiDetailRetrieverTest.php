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
 */
class ApiDetailRetrieverTest extends MediaWikiTestCase {

//

	public function testCheckMaxRevisionAggregatedBytes_setMax() {
		$this->setMwGlobals( [ 'wgFileImporterMaxAggregatedBytes' => 9 ] );
		$apiRetriever = new ApiDetailRetriever(
			$this->getMock( HttpApiLookup::class, [], [], '', false ),
			$this->getMock( HttpRequestExecutor::class, [], [], '', false ),
			new NullLogger()
		);

		$apiRetriever = TestingAccessWrapper::newFromObject( $apiRetriever );

		$this->setExpectedException( get_class(
			new LocalizedImportException( 'fileimporter-filetoolarge' ) )
		);
		$apiRetriever->checkMaxRevisionAggregatedBytes( [ 'imageinfo' => [ [ 'size' => 1000 ] ] ] );

		$this->assertTrue( true );
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
	public function testCheckMaxRevisionAggregatedBytes_passes( $input ) {
		$apiRetriever = new ApiDetailRetriever(
			$this->getMock( HttpApiLookup::class, [], [], '', false ),
			$this->getMock( HttpRequestExecutor::class, [], [], '', false ),
			new NullLogger()
		);

		$apiRetriever = TestingAccessWrapper::newFromObject( $apiRetriever );

		$apiRetriever->checkMaxRevisionAggregatedBytes( $input );

		$this->assertTrue( true );
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

	public function testCheckMaxRevisionAggregatedBytes_fails( $input ) {
		$apiRetriever = new ApiDetailRetriever(
			$this->getMock( HttpApiLookup::class, [], [], '', false ),
			$this->getMock( HttpRequestExecutor::class, [], [], '', false ),
			new NullLogger()
		);

		$apiRetriever = TestingAccessWrapper::newFromObject( $apiRetriever );

		$this->setExpectedException( get_class(
			new LocalizedImportException( 'fileimporter-filetoolarge' ) )
		);

		$apiRetriever->checkMaxRevisionAggregatedBytes( $input );

		$this->assertTrue( true );
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
	public function testInvalidResponse( $content, Exception $expected ) {
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
	public function testValidResponse( $sourceUrl, $titleString, $content, $expected ) {
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

	private function getMockHttpApiLookup() {
		$mock = $this->getMock( HttpApiLookup::class, [], [], '', false );
		$mock->expects( $this->once() )
			->method( 'getApiUrl' )
			->will( $this->returnValue( 'APIURL' ) );
		return $mock;
	}

	private function getMockHttpRequestExecutor( $titleString, $content ) {
		$mock = $this->getMock( HttpRequestExecutor::class, [], [], '', false );
		$mock->expects( $this->once() )
			->method( 'execute' )
			->with( $this->getExpectedExecuteRequestUrl( $titleString ) )
			->will( $this->returnValue( $this->getMockMWHttpRequest( $content ) ) );
		return $mock;
	}

	private function getExpectedExecuteRequestUrl( $titleString ) {
		return 'APIURL?action=query&format=json&titles=' . urlencode( $titleString ) .
				'&prop=imageinfo%7Crevisions&iilimit=500&iiurlwidth=800&iiurlheight=400'.
				'&iiprop=timestamp%7Cuser%7Cuserid%7Ccomment%7Ccanonicaltitle%7Curl%7Csize%7Csha1' .
				'&rvlimit=500&rvprop=flags%7Ctimestamp%7Cuser%7Csha1%7Ccontentmodel%7Ccomment%7Ccontent';
	}

	private function getMockMWHttpRequest( $content ) {
		$mock = $this->getMock( MWHttpRequest::class, [], [], '', false );
		$mock->method( 'getContent' )
			->will( $this->returnValue( $content ) );
		return $mock;
	}

}
