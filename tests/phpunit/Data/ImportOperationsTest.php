<?php

namespace FileImporter\Data\Test;

use FileImporter\Data\ImportOperations;
use FileImporter\Interfaces\ImportOperation;
use PHPUnit4And6Compat;
use RuntimeException;

/**
 * @covers \FileImporter\Data\ImportOperations
 *
 * @license GPL-2.0-or-later
 * @author Thiemo Kreuz
 */
class ImportOperationsTest extends \PHPUnit\Framework\TestCase {
	use PHPUnit4And6Compat;

	public function testCorrectCallOrder() {
		$operations = new ImportOperations();
		$this->assertTrue( $operations->prepare() );
		$this->assertTrue( $operations->validate() );
		$this->assertTrue( $operations->commit() );
	}

	public function testIncorrectCallOrder_validate() {
		$operations = new ImportOperations();
		$this->setExpectedException( RuntimeException::class );
		$operations->validate();
	}

	public function testIncorrectCallOrder_commit() {
		$operations = new ImportOperations();
		$this->setExpectedException( RuntimeException::class );
		$operations->commit();
	}

	public function testIncorrectCallOrder_prepareCommit() {
		$operations = new ImportOperations();
		$operations->prepare();
		$this->setExpectedException( RuntimeException::class );
		$operations->commit();
	}

	public function testOperationsAreCalledButStopOnFailure() {
		$allFail = $this->getMock( ImportOperation::class );
		$allFail->expects( $this->once() )->method( 'prepare' );
		$allFail->expects( $this->once() )->method( 'validate' );
		$allFail->expects( $this->once() )->method( 'commit' );

		$neverCalled = $this->getMock( ImportOperation::class );
		$neverCalled->expects( $this->never() )->method( 'prepare' );
		$neverCalled->expects( $this->never() )->method( 'validate' );
		$neverCalled->expects( $this->never() )->method( 'commit' );

		$operations = new ImportOperations();
		$operations->add( $allFail );
		$operations->add( $neverCalled );

		$this->assertFalse( $operations->prepare() );
		$this->assertFalse( $operations->validate() );
		$this->assertFalse( $operations->commit() );
	}

}
