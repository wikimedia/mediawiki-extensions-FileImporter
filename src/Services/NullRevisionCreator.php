<?php

namespace FileImporter\Services;

use CommentStoreComment;
use MediaWiki\Revision\MutableRevisionRecord;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\RevisionStore;
use Title;
use User;
use Wikimedia\Rdbms\ILoadBalancer;
use Wikimedia\Timestamp\ConvertibleTimestamp;

class NullRevisionCreator {

	/**
	 * @var ILoadBalancer
	 */
	private $loadBalancer;

	/**
	 * @var RevisionStore
	 */
	private $revisionStore;

	public function __construct(
		ILoadBalancer $loadBalancer,
		RevisionStore $revisionStore
	) {
		$this->loadBalancer = $loadBalancer;
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
		$dbw = $this->loadBalancer->getConnection( DB_MASTER );

		$revision = $this->revisionStore->newNullRevision(
			$dbw,
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

		return $this->revisionStore->insertRevisionOn( $revision, $dbw );
	}

}
