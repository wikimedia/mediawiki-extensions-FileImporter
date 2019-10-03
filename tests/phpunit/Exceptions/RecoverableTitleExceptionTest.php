<?php

namespace FileImporter\Tests\Exceptions;

use FileImporter\Data\ImportPlan;
use FileImporter\Exceptions\RecoverableTitleException;

/**
 * @covers \FileImporter\Exceptions\RecoverableTitleException
 *
 * @license GPL-2.0-or-later
 * @author Thiemo Kreuz
 */
class RecoverableTitleExceptionTest extends \PHPUnit\Framework\TestCase {

	public function testException() {
		$importPlan = $this->createMock( ImportPlan::class );

		$ex = new RecoverableTitleException( 'fileimporter-test-message', $importPlan );

		$this->assertSame( $importPlan, $ex->getImportPlan() );
	}

}
