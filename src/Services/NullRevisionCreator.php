<?php

namespace FileImporter\Services;

use CommentStoreComment;
use FileImporter\Data\FileRevision;
use FileImporter\Exceptions\ImportException;
use ManualLogEntry;
use MediaWiki\Revision\MutableRevisionRecord;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\User\UserIdentity;
use Title;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\ILoadBalancer;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @license GPL-2.0-or-later
 */
class NullRevisionCreator {

	private const ERROR_REVISION_CREATE = 'noNullRevisionCreated';

	/**
	 * @var ILoadBalancer
	 */
	private $loadBalancer;

	/**
	 * @var RevisionStore
	 */
	private $revisionStore;

	/**
	 * @param RevisionStore $revisionStore
	 * @param ILoadBalancer $loadBalancer
	 */
	public function __construct( RevisionStore $revisionStore, ILoadBalancer $loadBalancer ) {
		$this->loadBalancer = $loadBalancer;
		$this->revisionStore = $revisionStore;
	}

	/**
	 * @param Title $title
	 * @param FileRevision $fileRevision
	 * @param UserIdentity $user
	 * @param string $summary
	 *
	 * @throws ImportException e.g. when the $title was not created before
	 */
	public function createForLinkTarget(
		Title $title,
		FileRevision $fileRevision,
		UserIdentity $user,
		$summary
	) {
		$db = $this->loadBalancer->getConnection( DB_PRIMARY );
		$nullRevision = $this->revisionStore->newNullRevision(
			$db,
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

		$nullRevision = $this->revisionStore->insertRevisionOn( $nullRevision, $db );

		/** @see \ImportReporter::reportPage */
		$this->publishLogEntry(
			$db,
			'import',
			'interwiki',
			$nullRevision
		);

		/** @see \LocalFile::recordUpload2 */
		$this->publishLogEntry(
			$db,
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
	 * @param IDatabase $db
	 * @param string $type
	 * @param string $subtype
	 * @param RevisionRecord $revision
	 * @param array $parameters
	 */
	private function publishLogEntry(
		IDatabase $db,
		string $type,
		string $subtype,
		RevisionRecord $revision,
		array $parameters = []
	) {
		$logEntry = new ManualLogEntry( $type, $subtype );

		$logEntry->setParameters( $parameters );
		$logEntry->setPerformer( $revision->getUser() );
		$logEntry->setTarget( $revision->getPage() );
		$logEntry->setTimestamp( $revision->getTimestamp() );
		$logEntry->setComment( $revision->getComment()->text );
		$logEntry->setAssociatedRevId( $revision->getId() );
		$logEntry->addTags( 'fileimporter' );

		$logEntryId = $logEntry->insert( $db );
		$logEntry->publish( $logEntryId );
	}

}
