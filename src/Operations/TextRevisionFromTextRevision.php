<?php

namespace FileImporter\Operations;

use FileImporter\Data\TextRevision;
use FileImporter\Interfaces\ImportOperation;
use FileImporter\Services\WikiRevisionFactory;
use Title;
use WikiRevision;

class TextRevisionFromTextRevision implements ImportOperation {

	/**
	 * @var Title
	 */
	private $plannedTitle;

	/**
	 * @var TextRevision
	 */
	private $textRevision;

	/**
	 * @var WikiRevisionFactory
	 */
	private $wikiRevisionFactory;

	/**
	 * @var WikiRevision|null
	 */
	private $wikiRevision;

	public function __construct(
		Title $plannedTitle,
		TextRevision $textRevision,
		WikiRevisionFactory $wikiRevisionFactory
	) {
		$this->plannedTitle = $plannedTitle;
		$this->textRevision = $textRevision;
		$this->wikiRevisionFactory = $wikiRevisionFactory;
	}

	/**
	 * Method to prepare an operation. This will not commit anything to any persistent storage.
	 * For example, this could make API calls and validate data.
	 * @return bool success
	 */
	public function prepare() {
		$wikiRevision = $this->wikiRevisionFactory->newFromTextRevision( $this->textRevision );
		$wikiRevision->setTitle( $this->plannedTitle );
		$this->wikiRevision = $wikiRevision;
		return true;
	}

	/**
	 * Commit this operation to persistent storage.
	 * @return bool success
	 */
	public function commit() {
		return $this->wikiRevision->importOldRevision();
	}

	/**
	 * Rollback this operation to persistent storage.
	 * @return bool success
	 */
	public function rollback() {
		// TODO: Implement rollback() method.
		return false;
	}

}
