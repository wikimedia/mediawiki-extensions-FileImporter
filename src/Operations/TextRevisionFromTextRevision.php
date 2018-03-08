<?php

namespace FileImporter\Operations;

use FileImporter\Data\TextRevision;
use FileImporter\Interfaces\ImportOperation;
use FileImporter\Services\TextRevisionValidator;
use FileImporter\Services\WikiRevisionFactory;
use OldRevisionImporter;
use Psr\Log\LoggerInterface;
use WikiRevision;
use Title;
use User;

/**
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class TextRevisionFromTextRevision implements ImportOperation {

	/**
	 * @var Title
	 */
	private $plannedTitle;

	/**
	 * @var User user performing the import
	 */
	private $user;

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

	/**
	 * @var OldRevisionImporter
	 */
	private $importer;

	/**
	 * @var TextRevisionValidator
	 */
	private $textRevisionValidator;

	public function __construct(
		Title $plannedTitle,
		User $user,
		TextRevision $textRevision,
		WikiRevisionFactory $wikiRevisionFactory,
		OldRevisionImporter $importer,
		TextRevisionValidator $textRevisionValidator,
		LoggerInterface $logger
	) {
		$this->plannedTitle = $plannedTitle;
		$this->user = $user;
		$this->textRevision = $textRevision;
		$this->wikiRevisionFactory = $wikiRevisionFactory;
		$this->importer = $importer;
		$this->textRevisionValidator = $textRevisionValidator;
		$this->logger = $logger;
	}

	/**
	 * Method to prepare an operation. This will not commit anything to any persistent storage.
	 * @return bool success
	 */
	public function prepare() {
		$wikiRevision = $this->wikiRevisionFactory->newFromTextRevision( $this->textRevision );
		$wikiRevision->setTitle( $this->plannedTitle );

		$this->wikiRevision = $wikiRevision;

		return true;
	}

	/**
	 * Method to validate prepared data that should be committed.
	 * @return bool success
	 */
	public function validate() {
		$this->textRevisionValidator->validate(
			$this->plannedTitle,
			$this->user,
			$this->wikiRevision->getContent(),
			$this->wikiRevision->getComment(),
			$this->wikiRevision->getMinor()
		);

		return true;
	}

	/**
	 * Commit this operation to persistent storage.
	 * @return bool success
	 */
	public function commit() {
		$result = $this->importer->import( $this->wikiRevision );

		if ( !$result ) {
			$this->logger->error(
				__METHOD__ . ' failed to commit.',
				[ 'textRevision-getFields' => $this->textRevision->getFields() ]
			);
		}

		return $result;
	}

}
