<?php

namespace FileImporter\Exceptions;

use FileImporter\Data\ImportPlan;
use MessageSpecifier;
use Throwable;

/**
 * Exception thrown when an import has an issue with the planned title that can be
 * resolved by the user.
 *
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class RecoverableTitleException extends TitleException {

	/**
	 * @var ImportPlan
	 */
	private $importPlan;

	/**
	 * @param string|array|MessageSpecifier $messageSpec See Message::newFromSpecifier
	 * @param ImportPlan $importPlan ImportPlan to recover the import of.
	 * @param Throwable|null $previous
	 */
	public function __construct( $messageSpec, ImportPlan $importPlan, Throwable $previous = null ) {
		$this->importPlan = $importPlan;

		parent::__construct( $messageSpec, $previous );
	}

	/**
	 * @return ImportPlan
	 */
	public function getImportPlan() {
		return $this->importPlan;
	}

}
