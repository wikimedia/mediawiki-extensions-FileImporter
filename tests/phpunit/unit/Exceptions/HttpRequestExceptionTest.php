<?php

namespace FileImporter\Tests\Exceptions;

use FileImporter\Exceptions\HttpRequestException;
use MWHttpRequest;
use StatusValue;

/**
 * @covers \FileImporter\Exceptions\HttpRequestException
 *
 * @license GPL-2.0-or-later
 * @author Thiemo Kreuz
 */
class HttpRequestExceptionTest extends \MediaWikiUnitTestCase {

	public function testException() {
		$statusValue = new StatusValue();
		$httpRequest = $this->createMock( MWHttpRequest::class );
		$httpRequest->method( 'getStatus' )->willReturn( 200 );

		$ex = new HttpRequestException( $statusValue, $httpRequest );

		$this->assertSame( $statusValue, $ex->getStatusValue() );
		$this->assertSame( $httpRequest, $ex->getHttpRequest() );

		$this->assertNotEmpty( $ex->getMessage() );
		$this->assertSame( 200, $ex->getCode() );
		$this->assertNull( $ex->getPrevious() );
	}

}
