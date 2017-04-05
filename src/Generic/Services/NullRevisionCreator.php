<?php

namespace FileImporter\Generic\Services;

use Revision;
use RuntimeException;
use User;
use Wikimedia\Rdbms\LoadBalancer;

class NullRevisionCreator {

	/**
	 * @var LoadBalancer
	 */
	private $loadBalancer;

	public function __construct(
		LoadBalancer $loadBalancer
	) {
		$this->loadBalancer = $loadBalancer;
	}

	/**
	 * @param int $pageId
	 * @param User $user
	 * @param string $summary
	 * @param bool $minor
	 *
	 * @throws RuntimeException
	 * @return Revision
	 */
	public function createForLinkTarget( $pageId, User $user, $summary, $minor ) {
		$dbw = $this->loadBalancer->getConnection( DB_MASTER );

		$revision = Revision::newNullRevision(
			$dbw,
			$pageId,
			$summary,
			$minor,
			$user
		);

		if ( $revision === null ) {
			throw new RuntimeException( 'Failed to create null revision' );
		}

		$revision->insertOn( $dbw );

		return $revision;
	}

}
