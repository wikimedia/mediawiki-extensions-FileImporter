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
class HttpApiLookupTest extends \MediaWikiTestCase {
	use \PHPUnit4And6Compat;

	protected function setUp() {
		parent::setUp();

		$this->setMwGlobals( 'wgContLang', \Language::factory( 'qqx' ) );
	}

	public function provideHttpRequestErrors() {
		return [
			[ 404, 'File not found: //source.url' ],
			[ 200, 'Failed to discover API location from: &quot;//source.url&quot;.' ],
			[ 418, 'HTTP status code 418.' ],
			[ 301, '(error-message)' ],
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

		$this->setExpectedException( ImportException::class, $expectedMessage );
		$lookup->getApiUrl( new SourceUrl( '//source.url' ) );
	}

}
