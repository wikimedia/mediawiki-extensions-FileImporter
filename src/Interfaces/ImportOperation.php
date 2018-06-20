<?php

namespace FileImporter\Interfaces;

use FileImporter\Exceptions\ValidationException;

/**
 * @license GPL-2.0-or-later
 * @author Addshore
 */
interface ImportOperation {

	/**
	 * Method to prepare an operation. This will not commit anything to any persistent storage.
	 * @return bool success
	 */
	public function prepare();

	/**
	 * Method to validate prepared data that should be committed.
	 * @return bool success
	 * @throws ValidationException Failing validation can trigger specific error texts
	 */
	public function validate();

	/**
	 * Commit this operation to persistent storage.
	 * @return bool success
	 */
	public function commit();

}
