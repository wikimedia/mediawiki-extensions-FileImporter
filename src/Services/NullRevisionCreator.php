<?php

namespace FileImporter\Services;

use CommentStoreComment;
use MediaWiki\Revision\MutableRevisionRecord;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\RevisionStore;
use Title;
use User;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Timestamp\ConvertibleTimestamp;

class NullRevisionCreator {

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
	 * @param User $user
	 * @param string $summary
	 *
	 * @return RevisionRecord|false
	 */
	public function createForLinkTarget(
		Title $title,
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

		if ( $nullRevision === null ) {
			return false;
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

		return $nullRevision;
	}

}
