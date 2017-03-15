<?php

namespace FileImporter\Generic\Test;

use FileImporter\Generic\Exceptions\HttpRequestException;
use FileImporter\Generic\HttpRequestExecutor;
use MWHttpRequest;
use PHPUnit_Framework_TestCase;
use Psr\Log\LoggerInterface;
use Status;

class HttpRequestExecutorTest extends PHPUnit_Framework_TestCase {

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
		$executor = new HttpRequestExecutor();
		$factoryOverride = function ( $url, $options = null, $caller = __METHOD__ )
			use ( $testUrl, $expectedResult ) {

			$this->assertEquals( $testUrl, $url );
			$this->assertArrayHasKey( 'logger', $options );
			$this->assertArrayHasKey( 'followRedirects', $options );
			$this->assertInstanceOf( LoggerInterface::class, $options['logger'] );
			$this->assertTrue( $options['followRedirects'] );
			$this->assertEquals( $caller, HttpRequestExecutor::class . '::execute' );

			$request = $this->getMockBuilder( MWHttpRequest::class )
				->disableOriginalConstructor()
				->getMock();
			$status = Status::newGood();
			if ( !$expectedResult ) {
				$status->fatal( 'SomeFatal' );
			} else {
				$request->expects( $this->any() )
					->method( 'getContent' )
					->will( $this->returnValue( $expectedResult ) );
			}
			$request->expects( $this->any() )
				->method( 'execute' )
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
