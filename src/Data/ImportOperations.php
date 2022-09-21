<?php

namespace FileImporter\Data;

use FileImporter\Exceptions\ImportException;
use FileImporter\Interfaces\ImportOperation;
use StatusValue;

/**
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class ImportOperations implements ImportOperation {

	private const ERROR_EMPTY_OPERATIONS = 'emptyOperations';
	private const ERROR_OUT_OF_ORDER = 'outOfOrder';

	/**
	 * @var ImportOperation[]
	 */
	private $importOperations = [];

	/**
	 * @var int the state of this object, one of the class constants
	 */
	private $state = self::BUILDING;

	private const BUILDING = 0;
	private const PREPARE_RUN = 1;
	private const VALIDATE_RUN = 2;
	private const COMMIT_RUN = 3;

	/**
	 * @param ImportOperation $importOperation
	 */
	public function add( ImportOperation $importOperation ) {
		$this->throwExceptionOnBadState( self::BUILDING );
		$this->importOperations[] = $importOperation;
	}

	/**
	 * @param int $expectedState one of the class constants
	 *
	 * @throws ImportException when the expected state doesn't match
	 */
	private function throwExceptionOnBadState( $expectedState ) {
		if ( $this->state !== $expectedState ) {
			throw new ImportException(
				__CLASS__ . ' methods run out of order', self::ERROR_OUT_OF_ORDER );
		}
	}

	/**
	 * Run through one phase of import operations and collect status.
	 *
	 * @param int $expectedState State machine must be in this state to begin processing.
	 * @param int $nextState State machine will move to this state once processing begins.
	 * @param bool $stopOnError when True the state machine will exit early if errors are found
	 * @param callable $executor function( ImportOperation ): StatusValue,
	 *  callback to select which phase of the operation to run.
	 * @return StatusValue isOK if all steps succeed.  Accumulates warnings and may
	 *  include a final error explaining why not ok.
	 */
	private function runOperations(
		int $expectedState,
		int $nextState,
		bool $stopOnError,
		callable $executor
	): StatusValue {
		if ( !$this->importOperations ) {
			throw new ImportException(
				__CLASS__ . ' tried to run empty import operations',
				self::ERROR_EMPTY_OPERATIONS
			);
		}

		$this->throwExceptionOnBadState( $expectedState );
		$this->state = $nextState;

		$status = StatusValue::newGood();
		foreach ( $this->importOperations as $importOperation ) {
			$status->merge( $executor( $importOperation ) );

			if ( $stopOnError && !$status->isOK() ) {
				break;
			}
		}

		return $status;
	}

	/**
	 * Method to prepare an operation. This will not commit anything to any persistent storage.
	 * @return StatusValue isOK when all steps succeed
	 */
	public function prepare(): StatusValue {
		return $this->runOperations(
			self::BUILDING,
			self::PREPARE_RUN,
			true,
			static function ( ImportOperation $importOperation ) {
				return $importOperation->prepare();
			}
		);
	}

	/**
	 * Method to validate prepared content that should be committed.
	 * @return StatusValue isOK when all validation succeeds.  Specifics are accumulated
	 *  as errors and warnings.
	 */
	public function validate(): StatusValue {
		return $this->runOperations(
			self::PREPARE_RUN,
			self::VALIDATE_RUN,
			false,
			static function ( ImportOperation $importOperation ) {
				return $importOperation->validate();
			}
		);
	}

	/**
	 * Commit this operation to persistent storage.
	 * @return StatusValue isOK if all steps succeeded.
	 */
	public function commit(): StatusValue {
		return $this->runOperations(
			self::VALIDATE_RUN,
			self::COMMIT_RUN,
			true,
			static function ( ImportOperation $importOperation ) {
				return $importOperation->commit();
			}
		);
	}

}
