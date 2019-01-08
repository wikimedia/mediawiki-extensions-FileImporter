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
	public function createForLinkTarget( Title $title, User $user, $summary ) {
		$revision = $this->revisionStore->newNullRevision(
			$this->dbw,
			$title,
			CommentStoreComment::newUnsavedComment( $summary ),
			true,
			$user
		);

		if ( $revision === null ) {
			return false;
		}

		if ( $revision instanceof MutableRevisionRecord ) {
			$now = new ConvertibleTimestamp();
			// Place the null revision (along with the import log entry that mirrors the same
			// information) 1 second in the past, to guarantee it's listed before the later "post
			// import edit".
			$now->timestamp->sub( new \DateInterval( 'PT1S' ) );
			$revision->setTimestamp( $now->getTimestamp( TS_MW ) );
		}

		return $this->revisionStore->insertRevisionOn( $revision, $this->dbw );
	}

}
