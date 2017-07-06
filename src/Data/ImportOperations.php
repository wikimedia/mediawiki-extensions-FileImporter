<?php

namespace FileImporter\Data;

use FileImporter\Interfaces\ImportOperation;
use RuntimeException;

class ImportOperations implements ImportOperation {

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
	const COMMIT_RUN = 2;
	const ROLLBACK_RUN = 3;

	public function add( ImportOperation $importOperation ) {
		$this->importOperations[] = $importOperation;
	}

	/**
	 * @param int $expectedState one of the class constants
	 */
	private function throwExceptionOnBadState( $expectedState ) {
		if ( $this->state !== $expectedState ) {
			throw new RuntimeException( __CLASS__ . ' methods run out of order' );
		}
	}

	/**
	 * Method to prepare an operation. This will not commit anything to any persistent storage.
	 * For example, this could make API calls and validate data.
	 * @return bool success
	 */
	public function prepare() {
		$this->throwExceptionOnBadState( self::BUILDING );
		$this->state = self::PREPARE_RUN;
		foreach ( $this->importOperations as $importOperation ) {
			if ( !$importOperation->prepare() ) {
				// TODO log?
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
		$this->throwExceptionOnBadState( self::PREPARE_RUN );
		$this->state = self::COMMIT_RUN;
		foreach ( $this->importOperations as $importOperation ) {
			if ( !$importOperation->commit() ) {
				// TODO log?
				return false;
			}
		}
		return true;
	}

}
