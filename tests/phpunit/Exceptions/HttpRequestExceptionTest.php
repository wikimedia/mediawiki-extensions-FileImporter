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
class HttpRequestExceptionTest extends \PHPUnit\Framework\TestCase {

	public function testException() {
		$statusValue = new StatusValue();
		$httpRequest = $this->getMockBuilder( MWHttpRequest::class )
			->disableOriginalConstructor()
			->getMock();

		$ex = new HttpRequestException( $statusValue, $httpRequest );

		$this->assertSame( $statusValue, $ex->getStatusValue() );
		$this->assertSame( $httpRequest, $ex->getHttpRequest() );
	}

}
