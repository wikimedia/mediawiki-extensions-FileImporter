<?php

namespace FileImporter\Tests\Remote\MediaWiki;

use FileImporter\Data\SourceUrl;
use FileImporter\Exceptions\HttpRequestException;
use FileImporter\Exceptions\ImportException;
use FileImporter\Remote\MediaWiki\HttpApiLookup;
use FileImporter\Services\Http\HttpRequestExecutor;

/**
 * @covers \FileImporter\Remote\MediaWiki\HttpApiLookup
 *
 * @license GPL-2.0-or-later
 * @author Thiemo Kreuz
 */
class HttpApiLookupTest extends \MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
	}

	public function testResultCaching() {
		$request = $this->createMock( \MWHttpRequest::class );
		$request->method( 'getContent' )
			->willReturn( '<link rel="EditURI" href="//edit.uri?action=rsd">' );

		$executor = $this->createMock( HttpRequestExecutor::class );
		$executor->expects( $this->once() )
			->method( 'execute' )
			->willReturn( $request );

		$lookup = new HttpApiLookup( $executor );
		$sourceUrl = new SourceUrl( '//source.url' );

		$url1 = $lookup->getApiUrl( $sourceUrl );
		$this->assertSame( 'https://edit.uri', $url1, 'first call' );

		$url2 = $lookup->getApiUrl( $sourceUrl );
		$this->assertSame( $url1, $url2, 'second call' );
	}

	public function provideHttpRequestErrors() {
		return [
			[ 404, 'File not found: //source.url.' ],
			[ 200, 'Failed to discover API location from: //source.url.  ⧼error-message⧽' ],
			[ 418, 'Failed to discover API location from: //source.url. HTTP status code 418. '
				. '⧼error-message⧽' ],
			[ 301, 'Failed to discover API location from: //source.url. HTTP status code 301. '
				. '⧼error-message⧽' ],
		];
	}

	/**
	 * @dataProvider provideHttpRequestErrors
	 */
	public function testHttpRequestErrorHandling( $httpStatus, $expectedMessage ) {
		$status = \StatusValue::newFatal( 'error-message' );

		$request = $this->createMock( \MWHttpRequest::class );
		$request->method( 'getStatus' )
			->willReturn( $httpStatus );

		$executor = $this->createMock( HttpRequestExecutor::class );
		$executor->method( 'execute' )
			->willThrowException( new HttpRequestException( $status, $request ) );

		$lookup = new HttpApiLookup( $executor );

		$this->expectException( ImportException::class );
		$this->expectExceptionMessage( $expectedMessage );
		$lookup->getApiUrl( new SourceUrl( '//source.url' ) );
	}

}
