<?php

namespace FileImporter\Data;

use FileImporter\Exceptions\ImportException;
use FileImporter\Interfaces\ImportOperation;

/**
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class ImportOperations implements ImportOperation {

	const ERROR_OUT_OF_ORDER = 'outOfOrder';

	/**
	 * @var ImportOperation[]
	 */
	private $importOperations = [];

	/**
	 * @var int the state of this object, one of the class constants
	 */
	private $state = self::BUILDING;

	const BUILDING = 0;
	const PREPARE_RUN = 1;
	const VALIDATE_RUN = 2;
	const COMMIT_RUN = 3;

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
	 * Method to prepare an operation. This will not commit anything to any persistent storage.
	 * @return bool success
	 */
	public function prepare() {
		if ( $this->importOperations === [] ) {
			return false;
		}

		$this->throwExceptionOnBadState( self::BUILDING );
		$this->state = self::PREPARE_RUN;

		foreach ( $this->importOperations as $importOperation ) {
			if ( !$importOperation->prepare() ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Method to validate prepared content that should be committed.
	 * @return bool success
	 */
	public function validate() {
		$this->throwExceptionOnBadState( self::PREPARE_RUN );
		$this->state = self::VALIDATE_RUN;

		foreach ( $this->importOperations as $importOperation ) {
			if ( !$importOperation->validate() ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Commit this operation to persistent storage.
	 * @return bool success
	 */
	public function commit() {
		$this->throwExceptionOnBadState( self::VALIDATE_RUN );
		$this->state = self::COMMIT_RUN;

		foreach ( $this->importOperations as $importOperation ) {
			if ( !$importOperation->commit() ) {
				return false;
			}
		}

		return true;
	}

}
