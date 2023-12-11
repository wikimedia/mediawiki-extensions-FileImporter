<?php

namespace FileImporter\Tests\Services;

use FileImporter\Data\FileRevision;
use FileImporter\Services\NullRevisionCreator;
use MediaWiki\CommentStore\CommentStoreComment;
use MediaWiki\Revision\MutableRevisionRecord;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\Title\Title;

/**
 * @covers \FileImporter\Services\NullRevisionCreator
 *
 * Via {@see \ManualLogEntry::publish} this test still writes to the database, and there is
 * currently no way around this.
 * @group Database
 *
 * @license GPL-2.0-or-later
 */
class NullRevisionCreatorTest extends \MediaWikiIntegrationTestCase {

	public function testCreateForLinkTargetSuccess() {
		$this->clearHooks();
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

		$title = Title::makeTitle( NS_FILE, 'NullRevisionCreatorTest' );

		$fileRevision = $this->createMock( FileRevision::class );
		$user = $this->getTestUser()->getUser();
		$summary = 'Summary';
		$commentStore = new CommentStoreComment( null, $summary );

		$revisionRecord = $this->createMock( MutableRevisionRecord::class );
		$revisionRecord->method( 'getUser' )
			->willReturn( $user );
		$revisionRecord->method( 'getPage' )
			->willReturn( $title );
		$revisionRecord->method( 'getPageAsLinkTarget' )
			->willReturn( $title );
		$revisionRecord->method( 'getTimestamp' )
			->willReturn( '2023-08-09T19:42:19-04:00' );
		$revisionRecord->method( 'getComment' )
			->willReturn( $commentStore );
		$revisionRecord->expects( $this->exactly( 2 ) )
			->method( 'getId' )
			->willReturn( 1 );

		$revisionStore = $this->createMock( RevisionStore::class );
		$revisionStore->expects( $this->once() )
			->method( 'newNullRevision' )
			->with( $this->anything(), $title, $commentStore, true, $user )
			->willReturn( $revisionRecord );

		$revisionStore->expects( $this->once() )
			->method( 'insertRevisionOn' )
			->with( $revisionRecord, $this->anything() )
			->willReturn( $revisionRecord );

		$nullRevisionCreator = new NullRevisionCreator(
			$revisionStore,
			$this->getServiceContainer()->getDBLoadBalancerFactory()
		);

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
			$this->getServiceContainer()->getDBLoadBalancerFactory()
		);

		$this->expectException( \RuntimeException::class );
		$nullRevisionCreator->createForLinkTarget(
			Title::makeTitle( NS_FILE, __METHOD__ ),
			$this->createMock( FileRevision::class ),
			$this->getTestUser()->getUser(),
			''
		);
	}

}
