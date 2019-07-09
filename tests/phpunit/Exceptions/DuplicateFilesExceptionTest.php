<?php

namespace FileImporter\Tests\Exceptions;

use FakeDimensionFile;
use FileImporter\Exceptions\DuplicateFilesException;

/**
 * @covers \FileImporter\Exceptions\DuplicateFilesException
 *
 * @license GPL-2.0-or-later
 * @author Thiemo Kreuz
 */
class DuplicateFilesExceptionTest extends \PHPUnit\Framework\TestCase {

	public function testException() {
		$files = [ new FakeDimensionFile( [] ) ];

		$ex = new DuplicateFilesException( $files );

		$this->assertSame( $files, $ex->getFiles() );
	}

}
