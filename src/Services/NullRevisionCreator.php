<?php

namespace FileImporter\Services;

use CommentStoreComment;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\RevisionStore;
use Title;
use User;
use Wikimedia\Rdbms\ILoadBalancer;

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

		return $this->revisionStore->insertRevisionOn( $revision, $dbw );
	}

}
