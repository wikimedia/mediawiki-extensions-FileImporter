<?php

namespace FileImporter\Interfaces;

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
	 */
	public function validate();

	/**
	 * Commit this operation to persistent storage.
	 * @return bool success
	 */
	public function commit();

}
