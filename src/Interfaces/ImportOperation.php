<?php

namespace FileImporter\Interfaces;

/**
 * @license GPL-2.0-or-later
 * @author Addshore
 */
interface ImportOperation {

	/**
	 * Method to prepare an operation. This will not commit anything to any persistent storage.
	 * For example, this could make API calls and validate data.
	 * @return bool success
	 */
	public function prepare();

	/**
	 * Commit this operation to persistent storage.
	 * @return bool success
	 */
	public function commit();

}
