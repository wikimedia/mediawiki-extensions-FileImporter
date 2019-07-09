<?php

namespace FileImporter\Tests\Services\Http;

use FileImporter\Exceptions\HttpRequestException;
use FileImporter\Services\Http\HttpRequestExecutor;
use MWHttpRequest;
use PHPUnit4And6Compat;
use Psr\Log\LoggerInterface;
use Status;

/**
 * @covers \FileImporter\Services\Http\FileChunkSaver
 * @covers \FileImporter\Services\Http\HttpRequestExecutor
 *
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class HttpRequestExecutorTest extends \PHPUnit\Framework\TestCase {
	use PHPUnit4And6Compat;

	public function provideTestExecute() {
		return [
			[ 'http://example.com', false ],
			[ 'http://example.com', 'Some Real Content' ],
		];
	}

	/**
	 * @dataProvider provideTestExecute
	 */
	public function testExecute( $testUrl, $expectedResult ) {
		$executor = new HttpRequestExecutor( [], 0 );
		$factoryOverride = function ( $url, $options = null, $caller = __METHOD__ )
			use ( $testUrl, $expectedResult ) {
			$this->assertSame( $testUrl, $url );
			$this->assertArrayHasKey( 'logger', $options );
			$this->assertArrayHasKey( 'followRedirects', $options );
			$this->assertInstanceOf( LoggerInterface::class, $options['logger'] );
			$this->assertTrue( $options['followRedirects'] );
			$this->assertSame( $caller, HttpRequestExecutor::class . '::executeWithCallback' );

			$request = $this->createMock( MWHttpRequest::class );
			$status = Status::newGood();
			if ( !$expectedResult ) {
				$status->fatal( 'SomeFatal' );
			} else {
				$request->method( 'getContent' )
					->willReturn( $expectedResult );
			}
			$request->method( 'execute' )
				->willReturn( $status );

			return $request;
		};
		$executor->overrideRequestFactory( $factoryOverride );

		if ( !$expectedResult ) {
			$this->setExpectedException( HttpRequestException::class );
		}

		$request = $executor->execute( $testUrl );
		$this->assertSame( $expectedResult, $request->getContent() );
	}

	public function testExecutePost() {
		$testUrl = 'https://w.invalid/';
		$expectedResult = 'Some real content';
		$postData = [ 'a' => 'foo', 'b' => 'bar' ];
		$executor = new HttpRequestExecutor( [], 0 );
		$factoryOverride = function ( $url, $options = null, $caller = __METHOD__ )
		use ( $testUrl, $expectedResult, $postData ) {
			$this->assertSame( $testUrl, $url );
			$this->assertArrayHasKey( 'logger', $options );
			$this->assertArrayHasKey( 'followRedirects', $options );
			$this->assertInstanceOf( LoggerInterface::class, $options['logger'] );
			$this->assertTrue( $options['followRedirects'] );
			$this->assertEquals( 'POST', $options['method'] );
			$this->assertEquals( $postData, $options['postData'] );
			$this->assertSame( $caller, HttpRequestExecutor::class . '::executeWithCallback' );

			$request = $this->createMock( MWHttpRequest::class );
			$status = Status::newGood();
			if ( !$expectedResult ) {
				$status->fatal( 'SomeFatal' );
			} else {
				$request->method( 'getContent' )
					->willReturn( $expectedResult );
			}
			$request->method( 'execute' )
				->willReturn( $status );

			return $request;
		};
		$executor->overrideRequestFactory( $factoryOverride );

		if ( !$expectedResult ) {
			$this->setExpectedException( HttpRequestException::class );
		}

		$request = $executor->executePost( $testUrl, $postData );
		$this->assertSame( $expectedResult, $request->getContent() );
	}

}
