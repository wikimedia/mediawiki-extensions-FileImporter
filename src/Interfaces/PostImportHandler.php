<?php

namespace FileImporter\Interfaces;

use FileImporter\Data\ImportPlan;
use StatusValue;
use User;

/**
 * This interface is used to execute actions after a successful import.
 *
 * @license GPL-2.0-or-later
 */
interface PostImportHandler {

	/**
	 * @param ImportPlan $importPlan
	 * @param User $user
	 * @return StatusValue
	 */
	public function execute( ImportPlan $importPlan, User $user );

}
