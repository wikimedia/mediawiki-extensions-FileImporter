<?php

namespace FileImporter\Services;

use CommentStoreComment;
use FileImporter\Data\FileRevision;
use FileImporter\Exceptions\ImportException;
use ManualLogEntry;
use MediaWiki\Revision\MutableRevisionRecord;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\RevisionStore;
use Title;
use User;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Timestamp\ConvertibleTimestamp;

class NullRevisionCreator {

	const ERROR_REVISION_CREATE = 'noNullRevisionCreated';

	/**
	 * @var IDatabase
	 */
	private $dbw;

	/**
	 * @var RevisionStore
	 */
	private $revisionStore;

	public function __construct( RevisionStore $revisionStore, IDatabase $dbw ) {
		$this->dbw = $dbw;
		$this->revisionStore = $revisionStore;
	}

	/**
	 * @param Title $title
	 * @param FileRevision $fileRevision
	 * @param User $user
	 * @param string $summary
	 *
	 * @throws ImportException e.g. when the $title was not created before
	 */
	public function createForLinkTarget(
		Title $title,
		FileRevision $fileRevision,
		User $user,
		$summary
	) {
		$nullRevision = $this->revisionStore->newNullRevision(
			$this->dbw,
			$title,
			CommentStoreComment::newUnsavedComment( $summary ),
			true,
			$user
		);

		if ( !$nullRevision ) {
			throw new ImportException(
				'Failed to create import revision', self::ERROR_REVISION_CREATE );
		}

		if ( $nullRevision instanceof MutableRevisionRecord ) {
			$now = new ConvertibleTimestamp();
			// Place the null revision (along with the import log entry that mirrors the same
			// information) 1 second in the past, to guarantee it's listed before the later "post
			// import edit".
			$now->timestamp->sub( new \DateInterval( 'PT1S' ) );
			$nullRevision->setTimestamp( $now->getTimestamp( TS_MW ) );
		}

		$nullRevision = $this->revisionStore->insertRevisionOn( $nullRevision, $this->dbw );

		/** @see \ImportReporter::reportPage */
		$this->publishLogEntry(
			'import',
			'interwiki',
			$nullRevision
		);

		/** @see \LocalFile::recordUpload2 */
		$this->publishLogEntry(
			'upload',
			'upload',
			$nullRevision,
			[
				'img_sha1' => $fileRevision->getField( 'sha1' ),
				'img_timestamp' => $fileRevision->getField( 'timestamp' ),
			]
		);
	}

	/**
	 * @param string $type
	 * @param string $subtype
	 * @param RevisionRecord $revision
	 * @param array $parameters
	 */
	private function publishLogEntry(
		$type,
		$subtype,
		RevisionRecord $revision,
		array $parameters = []
	) {
		$logEntry = new ManualLogEntry( $type, $subtype );

		$logEntry->setParameters( $parameters );
		$logEntry->setPerformer( User::newFromIdentity( $revision->getUser() ) );
		$logEntry->setTarget( Title::newFromLinkTarget( $revision->getPageAsLinkTarget() ) );
		$logEntry->setTimestamp( $revision->getTimestamp() );
		$logEntry->setComment( $revision->getComment()->text );
		$logEntry->setAssociatedRevId( $revision->getId() );
		$logEntry->addTags( 'fileimporter' );

		$logEntryId = $logEntry->insert( $this->dbw );
		$logEntry->publish( $logEntryId );
	}

}
