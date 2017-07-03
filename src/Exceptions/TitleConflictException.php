<?php

namespace FileImporter\Exceptions;

use FileImporter\Data\ImportPlan;

class TitleConflictException extends ImportException {

	const LOCAL_TITLE = 1;
	const REMOTE_TITLE = 2;

	private $importPlan;
	private $conflictType;

	/**
	 * TitleConflictException constructor.
	 *
	 * @param ImportPlan $importPlan
	 * @param int $conflictType Either LOCAL_TITLE or REMOTE_TITLE
	 */
	public function __construct( ImportPlan $importPlan, $conflictType ) {
		$this->importPlan = $importPlan;
		$this->conflictType = $conflictType;
		parent::__construct( 'Title conflict detected' );
	}

	public function getImportPlan() {
		return $this->importPlan;
	}

	/**
	 * @return int
	 */
	public function getType() {
		return $this->conflictType;
	}

}
