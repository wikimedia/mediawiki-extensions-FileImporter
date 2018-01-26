<?php

namespace FileImporter\Operations;

use FileImporter\Data\TextRevision;
use FileImporter\Interfaces\ImportOperation;
use FileImporter\Services\WikiRevisionFactory;
use Psr\Log\LoggerInterface;
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
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * @var WikiRevision|null
	 */
	private $wikiRevision;

	public function __construct(
		Title $plannedTitle,
		TextRevision $textRevision,
		WikiRevisionFactory $wikiRevisionFactory,
		LoggerInterface $logger
	) {
		$this->plannedTitle = $plannedTitle;
		$this->textRevision = $textRevision;
		$this->wikiRevisionFactory = $wikiRevisionFactory;
		$this->logger = $logger;
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
		$result = $this->wikiRevision->importOldRevision();

		if ( !$result ) {
			$this->logger->error(
				__METHOD__ . ' failed to commit.',
				[ 'textRevision-getFields' => $this->textRevision->getFields() ]
			);
		}

		return $result;
	}

}
