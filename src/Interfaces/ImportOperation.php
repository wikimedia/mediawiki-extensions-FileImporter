<?php

namespace FileImporter\Interfaces;

use Status;

/**
 * @license GPL-2.0-or-later
 * @author Addshore
 */
interface ImportOperation {

	/**
	 * Method to prepare an operation. This will not commit anything to any persistent storage.
	 * @return Status isOK on success
	 */
	public function prepare(): Status;

	/**
	 * Method to validate prepared data that should be committed.
	 * @return Status isOK when validation succeeds
	 */
	public function validate(): Status;

	/**
	 * Commit this operation to persistent storage.
	 * @return Status isOK on success
	 */
	public function commit(): Status;

}
