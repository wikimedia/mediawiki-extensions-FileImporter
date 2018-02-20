<?php

namespace FileImporter\Services\Http\Test;

use FileImporter\Exceptions\HttpRequestException;
use FileImporter\Services\Http\HttpRequestExecutor;
use MWHttpRequest;
use Psr\Log\LoggerInterface;
use Status;

/**
 * @covers \FileImporter\Services\Http\HttpRequestExecutor
 */
class HttpRequestExecutorTest extends \PHPUnit\Framework\TestCase {

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
		$executor = new HttpRequestExecutor( 10 );
		$factoryOverride = function ( $url, $options = null, $caller = __METHOD__ )
			use ( $testUrl, $expectedResult ) {
			$this->assertEquals( $testUrl, $url );
			$this->assertArrayHasKey( 'logger', $options );
			$this->assertArrayHasKey( 'followRedirects', $options );
			$this->assertInstanceOf( LoggerInterface::class, $options['logger'] );
			$this->assertTrue( $options['followRedirects'] );
			$this->assertEquals( $caller, HttpRequestExecutor::class . '::executeWithCallback' );

			$request = $this->getMockBuilder( MWHttpRequest::class )
				->disableOriginalConstructor()
				->getMock();
			$status = Status::newGood();
			if ( !$expectedResult ) {
				$status->fatal( 'SomeFatal' );
			} else {
				$request->method( 'getContent' )
					->will( $this->returnValue( $expectedResult ) );
			}
			$request->method( 'execute' )
				->will( $this->returnValue( $status ) );

			return $request;
		};
		$executor->overrideRequestFactory( $factoryOverride );

		if ( !$expectedResult ) {
			$this->setExpectedException( HttpRequestException::class );
		}

		$request = $executor->execute( $testUrl );
		$this->assertEquals( $expectedResult, $request->getContent() );
	}

}
