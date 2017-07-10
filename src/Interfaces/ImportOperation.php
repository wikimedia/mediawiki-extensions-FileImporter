<?php

namespace FileImporter\Interfaces;

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

	/**
	 * Rollback this operation to persistent storage.
	 * @return bool success
	 */
	public function rollback();

}
