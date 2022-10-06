<?php

namespace FileImporter\Tests\Data;

use FileImporter\Data\ImportOperations;
use FileImporter\Exceptions\ImportException;
use FileImporter\Interfaces\ImportOperation;
use RuntimeException;
use StatusValue;

/**
 * @covers \FileImporter\Data\ImportOperations
 *
 * @license GPL-2.0-or-later
 * @author Thiemo Kreuz
 */
class ImportOperationsTest extends \MediaWikiUnitTestCase {

	public function testFailureOnEmptyOperations() {
		$operations = new ImportOperations();
		$this->expectException( ImportException::class );
		$operations->prepare();
	}

	public function testCorrectCallOrder() {
		$allSucceed = $this->newImportOperation(
			[
				'prepare' => 1,
				'validate' => 1,
				'commit' => 1
			],
			true
		);
		$operations = new ImportOperations();
		$operations->add( $allSucceed );

		$this->assertTrue( $operations->prepare()->isOK(), 'prepare' );
		$this->assertTrue( $operations->validate()->isOK(), 'validate' );
		$this->assertTrue( $operations->commit()->isOK(), 'commit' );
	}

	public function testIncorrectCallOrder_add() {
		$operations = new ImportOperations();
		$operations->add( $this->createMock( ImportOperation::class ) );
		$operations->prepare();

		$this->expectException( RuntimeException::class );
		$operations->add( $this->createMock( ImportOperation::class ) );
	}

	public function testIncorrectCallOrder_validate() {
		$operations = new ImportOperations();
		$this->expectException( RuntimeException::class );
		$operations->validate();
	}

	public function testIncorrectCallOrder_commit() {
		$operations = new ImportOperations();
		$this->expectException( RuntimeException::class );
		$operations->commit();
	}

	public function testIncorrectCallOrder_prepareCommit() {
		$noop = $this->createMock( ImportOperation::class );
		$noop->expects( $this->once() )
			->method( 'prepare' )
			->willReturn( StatusValue::newGood() );
		$noop->expects( $this->never() )
			->method( 'commit' )
			->willReturn( StatusValue::newGood() );

		$operations = new ImportOperations();
		$operations->add( $noop );

		$operations->prepare();
		$this->expectException( RuntimeException::class );
		$operations->commit();
	}

	public function testOperationsAreCalledOnlyValidateDoesNotStopOnFailure() {
		$allFail = $this->newImportOperation(
			[
				'prepare' => 1,
				'validate' => 1,
				'commit' => 1
			],
			false
		);
		$prepareCommitNeverCalled = $this->newImportOperation(
			[
				'prepare' => 0,
				'validate' => 1,
				'commit' => 0
			],
			false
		);

		$operations = new ImportOperations();
		$operations->add( $allFail );
		$operations->add( $prepareCommitNeverCalled );

		$this->assertFalse( $operations->prepare()->isOK() );
		$this->assertFalse( $operations->validate()->isOK() );
		$this->assertFalse( $operations->commit()->isOK() );
	}

	/**
	 * @param array $calls Number of calls to each of the steps
	 * @param bool $success
	 *
	 * @return ImportOperation
	 */
	private function newImportOperation( $calls, $success = false ): ImportOperation {
		$mock = $this->createMock( ImportOperation::class );
		$mock->expects( $this->exactly( $calls['prepare'] ) )
			->method( 'prepare' )
			->willReturn( $success ? StatusValue::newGood() : StatusValue::newFatal( '' ) );
		$mock->expects( $this->exactly( $calls['validate'] ) )
			->method( 'validate' )
			->willReturn( $success ? StatusValue::newGood() : StatusValue::newFatal( '' ) );
		$mock->expects( $this->exactly( $calls['commit'] ) )
			->method( 'commit' )
			->willReturn( $success ? StatusValue::newGood() : StatusValue::newFatal( '' ) );
		return $mock;
	}

}
