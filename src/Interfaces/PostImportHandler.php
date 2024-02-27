<?php

namespace FileImporter\Interfaces;

use FileImporter\Data\ImportPlan;
use MediaWiki\User\User;
use StatusValue;

/**
 * This interface is used to execute actions after a successful import.
 *
 * @license GPL-2.0-or-later
 */
interface PostImportHandler {

	/**
	 * @return StatusValue Might contain one or more warnings. The status's value is always a
	 *  success message, since the import was done before.
	 */
	public function execute( ImportPlan $importPlan, User $user ): StatusValue;

}
