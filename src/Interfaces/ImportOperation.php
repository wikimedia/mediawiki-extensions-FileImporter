<?php

namespace FileImporter\Interfaces;

use StatusValue;

/**
 * @license GPL-2.0-or-later
 * @author Addshore
 */
interface ImportOperation {

	/**
	 * Method to prepare an operation. This will not commit anything to any persistent storage.
	 * @return StatusValue isOK on success
	 */
	public function prepare(): StatusValue;

	/**
	 * Method to validate prepared data that should be committed.
	 * @return StatusValue isOK when validation succeeds
	 */
	public function validate(): StatusValue;

	/**
	 * Commit this operation to persistent storage.
	 * @return StatusValue isOK on success
	 */
	public function commit(): StatusValue;

}
