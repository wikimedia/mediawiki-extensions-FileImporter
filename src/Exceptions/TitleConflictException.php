<?php

namespace FileImporter\Exceptions;

use FileImporter\Data\ImportPlan;

class TitleConflictException extends ImportException {

	const LOCAL_TITLE = 1;
	const REMOTE_TITLE = 2;

	private $plan;
	private $conflictType;

	/**
	 * TitleConflictException constructor.
	 *
	 * @param ImportPlan $plan
	 * @param int $conflictType Either LOCAL_TITLE or REMOTE_TITLE
	 */
	public function __construct( ImportPlan $plan, $conflictType ) {
		$this->plan = $plan;
		$this->conflictType = $conflictType;
		parent::__construct( 'Title conflict detected' );
	}

	public function getPlan() {
		return $this->plan;
	}

	/**
	 * @return int
	 */
	public function getType() {
		return $this->conflictType;
	}

}
