<?php

namespace FileImporter\Tests\Services;

use CommentStoreComment;
use FileImporter\Services\NullRevisionCreator;
use MediaWiki\Storage\RevisionRecord;
use MediaWiki\Storage\RevisionStore;
use PHPUnit4And6Compat;
use Title;
use User;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\ILoadBalancer;

/**
 * @covers \FileImporter\Services\NullRevisionCreator
 *
 * @license GPL-2.0-or-later
 */
class NullRevisionCreatorTest extends \PHPUnit\Framework\TestCase {
	use PHPUnit4And6Compat;

	public function testCreateForLinkTargetSuccess() {
		$title = $this->createMock( Title::class );
		$user = $this->createMock( User::class );
		$dbw = $this->createMock( IDatabase::class );
		$summary = 'Summary';
		$commentStore = new CommentStoreComment( null, $summary );
		$revisionRecord = $this->createMock( RevisionRecord::class );

		$loadBalancer = $this->createILoadBalancerMock( $dbw );

		$revisionStore = $this->createMock( RevisionStore::class );
		$revisionStore->expects( $this->once() )
			->method( 'newNullRevision' )
			->with( $dbw, $title, $commentStore, true, $user )
			->willReturn( $revisionRecord );

		$revisionStore->expects( $this->once() )
			->method( 'insertRevisionOn' )
			->with( $revisionRecord, $dbw )
			->willReturn( $revisionRecord );

		$nullRevisionCreator = new NullRevisionCreator( $loadBalancer, $revisionStore );

		$this->assertSame(
			$revisionRecord,
			$nullRevisionCreator->createForLinkTarget( $title, $user, $summary )
		);
	}

	public function testCreateForLinkTargetFailure() {
		$loadBalancer = $this->createILoadBalancerMock( $this->createMock( IDatabase::class ) );

		$revisionStore = $this->createMock( RevisionStore::class );
		$revisionStore->expects( $this->once() )
			->method( 'newNullRevision' )
			->willReturn( null );

		$revisionStore->expects( $this->never() )
			->method( 'insertRevisionOn' );

		$nullRevisionCreator = new NullRevisionCreator( $loadBalancer, $revisionStore );

		$this->assertFalse(
			$nullRevisionCreator->createForLinkTarget(
				$this->createMock( Title::class ),
				$this->createMock( User::class ),
				''
			)
		);
	}

	/**
	 * @return ILoadBalancer
	 */
	private function createILoadBalancerMock( IDatabase $database ) {
		$loadBalancer = $this->createMock( ILoadBalancer::class );
		$loadBalancer->expects( $this->once() )
			->method( 'getConnection' )
			->willReturn( $database );
		return $loadBalancer;
	}

}
