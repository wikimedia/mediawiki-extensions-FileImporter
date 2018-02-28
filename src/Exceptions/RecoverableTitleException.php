<?php

namespace FileImporter\Exceptions;

use FileImporter\Data\ImportPlan;
use MessageSpecifier;

/**
 * Exception thrown when an import has an issue with the planned title that can be
 * resolved by the user.
 */
class RecoverableTitleException extends TitleException {

	/**
	 * @var ImportPlan
	 */
	private $importPlan;

	/**
	 * @param string|array|MessageSpecifier $messageSpec See Message::newFromSpecifier
	 * @param ImportPlan $importPlan ImportPlan to recover the import of.
	 */
	public function __construct( $messageSpec, ImportPlan $importPlan ) {
		$this->importPlan = $importPlan;
		parent::__construct( $messageSpec );
	}

	public function getImportPlan() {
		return $this->importPlan;
	}

}
