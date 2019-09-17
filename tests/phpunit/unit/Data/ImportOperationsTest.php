<?php

namespace FileImporter\Tests\Data;

use FileImporter\Data\ImportOperations;
use FileImporter\Interfaces\ImportOperation;
use RuntimeException;

/**
 * @covers \FileImporter\Data\ImportOperations
 *
 * @license GPL-2.0-or-later
 * @author Thiemo Kreuz
 */
class ImportOperationsTest extends \MediaWikiUnitTestCase {

	public function testFailureOnEmptyOperations() {
		$operations = new ImportOperations();
		$this->assertFalse( $operations->prepare() );
	}

	public function testCorrectCallOrder() {
		$allSucceed = $this->newImportOperation( 1, true );
		$operations = new ImportOperations();
		$operations->add( $allSucceed );

		$this->assertTrue( $operations->prepare(), 'prepare' );
		$this->assertTrue( $operations->validate(), 'validate' );
		$this->assertTrue( $operations->commit(), 'commit' );
	}

	public function testIncorrectCallOrder_add() {
		$operations = new ImportOperations();
		$operations->add( $this->createMock( ImportOperation::class ) );
		$operations->prepare();

		$this->setExpectedException( RuntimeException::class );
		$operations->add( $this->createMock( ImportOperation::class ) );
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
		$allFail = $this->newImportOperation( 1, false );
		$neverCalled = $this->newImportOperation( 0 );

		$operations = new ImportOperations();
		$operations->add( $allFail );
		$operations->add( $neverCalled );

		$this->assertFalse( $operations->prepare() );
		$this->assertFalse( $operations->validate() );
		$this->assertFalse( $operations->commit() );
	}

	/**
	 * @param int $calls Number of calls to each of the steps
	 * @param bool $success
	 *
	 * @return ImportOperation
	 */
	private function newImportOperation( $calls, $success = false ) : ImportOperation {
		$mock = $this->getMock( ImportOperation::class );
		$mock->expects( $this->exactly( $calls ) )
			->method( 'prepare' )
			->willReturn( $success );
		$mock->expects( $this->exactly( $calls ) )
			->method( 'validate' )
			->willReturn( $success );
		$mock->expects( $this->exactly( $calls ) )
			->method( 'commit' )
			->willReturn( $success );
		return $mock;
	}

}
