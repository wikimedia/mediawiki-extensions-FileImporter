<?php

namespace FileImporter\Interfaces;

use FileImporter\Data\ImportPlan;
use StatusValue;
use User;

/**
 * This interface is used to execute actions after a successful import.
 */
interface PostImportHandler {

	/**
	 * @param ImportPlan $importPlan
	 * @param User $user
	 * @return StatusValue
	 */
	public function execute( ImportPlan $importPlan, User $user );

}
