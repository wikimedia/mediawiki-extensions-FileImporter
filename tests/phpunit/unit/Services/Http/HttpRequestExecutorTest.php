<?php

namespace FileImporter\Tests\Services\Http;

use FileImporter\Exceptions\HttpRequestException;
use FileImporter\Services\Http\HttpRequestExecutor;
use MediaWiki\Http\HttpRequestFactory;
use MWHttpRequest;
use Psr\Log\LoggerInterface;
use StatusValue;

/**
 * @covers \FileImporter\Services\Http\FileChunkSaver
 * @covers \FileImporter\Services\Http\HttpRequestExecutor
 *
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class HttpRequestExecutorTest extends \MediaWikiUnitTestCase {

	public static function provideTestExecute() {
		return [
			[ '//example.com', false ],
			[ '//example.com', 'Some Real Content' ],
		];
	}

	/**
	 * @dataProvider provideTestExecute
	 */
	public function testExecute( string $testUrl, $expectedResult ) {
		$httpRequestFactory = $this->createMock( HttpRequestFactory::class );
		$httpRequestFactory->method( 'create' )
			->willReturnCallback( function ( string $url, array $options, string $caller ) use (
				$testUrl, $expectedResult
			): MWHttpRequest {
				$this->assertSame( $testUrl, $url );
				$this->assertArrayHasKey( 'logger', $options );
				$this->assertArrayHasKey( 'followRedirects', $options );
				$this->assertInstanceOf( LoggerInterface::class, $options['logger'] );
				$this->assertTrue( $options['followRedirects'] );
				$this->assertSame( [ 'ip' => '9.9.9.9' ], $options['originalRequest'] );
				$this->assertSame( HttpRequestExecutor::class . '::executeHttpRequest', $caller );
				$this->assertStringContainsString( 'FileImporter', $options['userAgent'] );

				$request = $this->createMock( MWHttpRequest::class );
				$status = StatusValue::newGood();
				if ( !$expectedResult ) {
					$status->fatal( 'SomeFatal' );
				} else {
					$request->method( 'getContent' )
						->willReturn( $expectedResult );
				}
				$request->method( 'execute' )
					->willReturn( $status );

				return $request;
			} );
		$executor = new HttpRequestExecutor( $httpRequestFactory, [ 'originalRequest' => [ 'ip' => '9.9.9.9' ] ], 0 );

		if ( !$expectedResult ) {
			$this->expectException( HttpRequestException::class );
		}

		$request = $executor->execute( $testUrl );
		$this->assertSame( $expectedResult, $request->getContent() );
	}

	public function testExecutePost() {
		$testUrl = '//w.invalid/';
		$expectedResult = 'Some real content';
		$postData = [ 'a' => 'foo', 'b' => 'bar' ];
		$httpRequestFactory = $this->createMock( HttpRequestFactory::class );
		$httpRequestFactory->method( 'create' )
			->willReturnCallback( function (
				$url, $options = null, $caller = __METHOD__
			) use (
				$testUrl, $expectedResult, $postData
			) {
				$this->assertSame( $testUrl, $url );
				$this->assertArrayHasKey( 'logger', $options );
				$this->assertArrayHasKey( 'followRedirects', $options );
				$this->assertInstanceOf( LoggerInterface::class, $options['logger'] );
				$this->assertTrue( $options['followRedirects'] );
				$this->assertSame( [ 'ip' => '9.9.9.9' ], $options['originalRequest'] );
				$this->assertEquals( 'POST', $options['method'] );
				$this->assertEquals( $postData, $options['postData'] );
				$this->assertSame( HttpRequestExecutor::class . '::executeHttpRequest', $caller );
				$this->assertStringContainsString( 'FileImporter', $options['userAgent'] );

				$request = $this->createMock( MWHttpRequest::class );
				$status = StatusValue::newGood();
				if ( !$expectedResult ) {
					$status->fatal( 'SomeFatal' );
				} else {
					$request->method( 'getContent' )
						->willReturn( $expectedResult );
				}
				$request->method( 'execute' )
					->willReturn( $status );

				return $request;
			} );
		$executor = new HttpRequestExecutor( $httpRequestFactory, [ 'originalRequest' => [ 'ip' => '9.9.9.9' ] ], 0 );

		if ( !$expectedResult ) {
			$this->expectException( HttpRequestException::class );
		}

		$request = $executor->executePost( $testUrl, $postData );
		$this->assertSame( $expectedResult, $request->getContent() );
	}

}
