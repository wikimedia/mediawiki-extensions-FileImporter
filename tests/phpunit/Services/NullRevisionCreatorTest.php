<?php

namespace FileImporter\Tests\Services;

use CommentStoreComment;
use FileImporter\Data\FileRevision;
use FileImporter\Services\NullRevisionCreator;
use MediaWiki\Revision\MutableRevisionRecord;
use MediaWiki\Revision\RevisionStore;
use Title;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\ILoadBalancer;

/**
 * @covers \FileImporter\Services\NullRevisionCreator
 *
 * Via {@see \ManualLogEntry::publish} this test still writes to the database, and there is
 * currently no way around this.
 * @group Database
 *
 * @license GPL-2.0-or-later
 */
class NullRevisionCreatorTest extends \MediaWikiTestCase {

	public function testCreateForLinkTargetSuccess() {
		$this->setMwGlobals( 'wgHooks', [
			'ChangeTagsAfterUpdateTags' => [ function (
				$tagsToAdd,
				$tagsToRemove,
				$prevTags,
				$rc_id,
				$rev_id,
				$log_id,
				$params,
				$rc,
				$user
			) {
				$this->assertSame( [ 'fileimporter' ], $tagsToAdd );
				$this->assertSame( 1, $rev_id );
			} ],
		] );

		$title = Title::makeTitle( NS_FILE, __METHOD__ );
		$fileRevision = $this->createMock( FileRevision::class );
		$user = $this->getTestUser()->getUser();
		$lb = $this->createLoadBalancer();
		$dbw = $lb->getConnection( 0 );
		$summary = 'Summary';
		$commentStore = new CommentStoreComment( null, $summary );

		$revisionRecord = $this->createMock( MutableRevisionRecord::class );
		$revisionRecord->method( 'getUser' )
			->willReturn( $user );
		$revisionRecord->method( 'getPageAsLinkTarget' )
			->willReturn( $title );
		$revisionRecord->method( 'getComment' )
			->willReturn( $commentStore );
		$revisionRecord->expects( $this->exactly( 2 ) )
			->method( 'getId' )
			->willReturn( 1 );

		$revisionStore = $this->createMock( RevisionStore::class );
		$revisionStore->expects( $this->once() )
			->method( 'newNullRevision' )
			->with( $dbw, $title, $commentStore, true, $user )
			->willReturn( $revisionRecord );

		$revisionStore->expects( $this->once() )
			->method( 'insertRevisionOn' )
			->with( $revisionRecord, $dbw )
			->willReturn( $revisionRecord );

		$nullRevisionCreator = new NullRevisionCreator( $revisionStore, $lb );

		$nullRevisionCreator->createForLinkTarget( $title, $fileRevision, $user, $summary );
	}

	public function testCreateForLinkTargetFailure() {
		$revisionStore = $this->createMock( RevisionStore::class );
		$revisionStore->expects( $this->once() )
			->method( 'newNullRevision' );
		$revisionStore->expects( $this->never() )
			->method( 'insertRevisionOn' );

		$nullRevisionCreator = new NullRevisionCreator(
			$revisionStore,
			$this->createLoadBalancer()
		);

		$this->expectException( \RuntimeException::class );
		$nullRevisionCreator->createForLinkTarget(
			Title::makeTitle( NS_FILE, __METHOD__ ),
			$this->createMock( FileRevision::class ),
			$this->getTestUser()->getUser(),
			''
		);
	}

	private function createLoadBalancer() : ILoadBalancer {
		$dbw = $this->createMock( IDatabase::class );
		$dbw->method( 'insertId' )
			->willReturn( 1 );
		$dbw->method( 'selectRow' )
			->willReturn( (object)[ 'actor_id' => '1' ] );

		$lb = $this->createMock( ILoadBalancer::class );
		$lb->method( 'getConnection' )->willReturn( $dbw );
		return $lb;
	}

}
