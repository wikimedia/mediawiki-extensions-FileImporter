<?php

namespace FileImporter\Tests\Exceptions;

use FileImporter\Exceptions\HttpRequestException;
use PHPUnit4And6Compat;
use MWHttpRequest;
use StatusValue;

/**
 * @covers \FileImporter\Exceptions\HttpRequestException
 *
 * @license GPL-2.0-or-later
 * @author Thiemo Kreuz
 */
class HttpRequestExceptionTest extends \PHPUnit\Framework\TestCase {
	use PHPUnit4And6Compat;

	public function testException() {
		$statusValue = new StatusValue();
		$httpRequest = $this->createMock( MWHttpRequest::class );

		$ex = new HttpRequestException( $statusValue, $httpRequest );

		$this->assertSame( $statusValue, $ex->getStatusValue() );
		$this->assertSame( $httpRequest, $ex->getHttpRequest() );
	}

}
