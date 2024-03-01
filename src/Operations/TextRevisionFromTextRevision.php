<?php

namespace FileImporter\Operations;

use FileImporter\Data\TextRevision;
use FileImporter\Interfaces\ImportOperation;
use FileImporter\Services\FileTextRevisionValidator;
use FileImporter\Services\WikiRevisionFactory;
use MediaWiki\Permissions\RestrictionStore;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use OldRevisionImporter;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use StatusValue;
use WikiRevision;

/**
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class TextRevisionFromTextRevision implements ImportOperation {

	private Title $plannedTitle;
	/** @var User user performing the import */
	private User $user;
	private TextRevision $textRevision;
	private WikiRevisionFactory $wikiRevisionFactory;
	/** @var WikiRevision|null */
	private $wikiRevision;
	private OldRevisionImporter $importer;
	private FileTextRevisionValidator $textRevisionValidator;
	private RestrictionStore $restrictionStore;
	private LoggerInterface $logger;

	public function __construct(
		Title $plannedTitle,
		User $user,
		TextRevision $textRevision,
		WikiRevisionFactory $wikiRevisionFactory,
		OldRevisionImporter $importer,
		FileTextRevisionValidator $textRevisionValidator,
		RestrictionStore $restrictionStore,
		LoggerInterface $logger = null
	) {
		$this->plannedTitle = $plannedTitle;
		$this->user = $user;
		$this->textRevision = $textRevision;
		$this->wikiRevisionFactory = $wikiRevisionFactory;
		$this->importer = $importer;
		$this->textRevisionValidator = $textRevisionValidator;
		$this->restrictionStore = $restrictionStore;
		$this->logger = $logger ?? new NullLogger();
	}

	/**
	 * Method to prepare an operation. This will not commit anything to any persistent storage.
	 * @return StatusValue isOK on success
	 */
	public function prepare(): StatusValue {
		$wikiRevision = $this->wikiRevisionFactory->newFromTextRevision( $this->textRevision );
		$wikiRevision->setTitle( $this->plannedTitle );

		$this->wikiRevision = $wikiRevision;

		return StatusValue::newGood();
	}

	/**
	 * Method to validate prepared data that should be committed.
	 * @return StatusValue isOK on success
	 */
	public function validate(): StatusValue {
		// Even administrators should not (accidentially) move a file to a protected file name
		if ( $this->restrictionStore->isProtected( $this->plannedTitle ) ) {
			return StatusValue::newFatal( 'fileimporter-filenameerror-protected' );
		}

		return $this->textRevisionValidator->validate(
			$this->plannedTitle,
			$this->user,
			$this->wikiRevision->getContent(),
			$this->wikiRevision->getComment(),
			$this->wikiRevision->getMinor()
		);
	}

	/**
	 * Commit this operation to persistent storage.
	 * @return StatusValue isOK on success
	 */
	public function commit(): StatusValue {
		$result = $this->importer->import( $this->wikiRevision );

		if ( $result ) {
			return StatusValue::newGood();
		} else {
			$this->logger->error(
				__METHOD__ . ' failed to commit.',
				[ 'textRevision-getFields' => $this->textRevision->getFields() ]
			);
			return StatusValue::newFatal( 'fileimporter-importfailed' );
		}
	}

	/**
	 * @return WikiRevision|null
	 */
	public function getWikiRevision() {
		return $this->wikiRevision;
	}

}
