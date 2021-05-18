<?php

namespace FileImporter\Tests\Exceptions;

use FileImporter\Exceptions\ImportException;

/**
 * @covers \FileImporter\Exceptions\ImportException
 *
 * @license GPL-2.0-or-later
 */
class ImportExceptionTest extends \MediaWikiUnitTestCase {

	public function provideErrorCodes() {
		return [
			[ 0, 0 ],
			[ 404, 404 ],
			[ '404', 404 ],
			[ 1.2, 1.2 ],
			[ '1.2', '1.2' ],
			[ 'string-error-code', 'string-error-code' ],
			[ '404-ish', '404-ish' ],
		];
	}

	/**
	 * @dataProvider provideErrorCodes
	 */
	public function testException( $code, $expected ) {
		$ex = new ImportException( 'Some message', $code );

		$this->assertSame( $expected, $ex->getCode() );
		$this->assertSame( 'Some message', $ex->getMessage() );
	}

}
