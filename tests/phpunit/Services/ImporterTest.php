<?php

namespace FileImporter\Tests\Services;

use Article;
use ChangeTags;
use CommentStoreComment;
use DatabaseLogEntry;
use FileImporter\Data\FileRevision;
use FileImporter\Data\FileRevisions;
use FileImporter\Data\ImportDetails;
use FileImporter\Data\ImportPlan;
use FileImporter\Data\ImportRequest;
use FileImporter\Data\SourceUrl;
use FileImporter\Data\TextRevision;
use FileImporter\Data\TextRevisions;
use FileImporter\Services\FileTextRevisionValidator;
use FileImporter\Services\Http\HttpRequestExecutor;
use FileImporter\Services\Importer;
use FileImporter\Services\WikiRevisionFactory;
use ImportableOldRevisionImporter;
use ImportableUploadRevisionImporter;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\User\UserIdentityLookup;
use MediaWiki\User\UserIdentityValue;
use Message;
use MessageLocalizer;
use Psr\Log\NullLogger;
use TitleValue;

/**
 * @covers \FileImporter\Services\Importer
 *
 * @group Database
 *
 * @license GPL-2.0-or-later
 * @author Christoph Jauera <christoph.jauera@wikimedia.de>
 */
class ImporterTest extends \MediaWikiIntegrationTestCase {

	private const TEST_FILE_SRC = __DIR__ . '/../res/testfile.png';
	private const TEST_FILE2_SRC = __DIR__ . '/../res/testfile2.png';
	// Random number (actually the SHA1 of this file) to not conflict with other tests
	private const TITLE = 'Test-29e7a6ff58c5eb980fc0642a13b59cb9c5a3cf55.png';

	protected function setUp(): void {
		parent::setUp();

		$this->tablesUsed[] = 'change_tag';
		$this->tablesUsed[] = 'change_tag_def';
		$this->tablesUsed[] = 'logging';

		$this->overrideConfigValues( [
			'FileImporterCommentForPostImportRevision' => 'imported from $1',
			'FileImporterTextForPostImportRevision' => '<!--imported from $1-->',
		] );

		$this->clearHooks();
	}

	protected function tearDown(): void {
		parent::tearDown();

		// avoid file leftovers when repeatedly run on a local system
		$file = MediaWikiServices::getInstance()->getRepoGroup()->getLocalRepo()
			->newFile( self::TITLE );
		if ( $file->exists() ) {
			$reason = 'This was just from a PHPUnit test.';
			$file->deleteFile( $reason, $this->getTestSysop()->getUser() );
		}
	}

	public function testImport() {
		$revisionLookup = MediaWikiServices::getInstance()->getRevisionLookup();
		$importer = $this->newImporter();
		$plan = $this->newImportPlan();
		$title = $plan->getTitle();

		$this->assertFalse( $title->exists() );

		$targetUser = $this->getTestUser()->getUser();

		$importer->import(
			$targetUser,
			$plan
		);

		// assert page was locally created
		$this->assertTrue( $title->exists() );
		$this->assertTrue( $title->isWikitextPage() );

		// assert original revision was imported correctly
		$firstRevision = $revisionLookup->getFirstRevision( $title );

		$this->assertFalse( $firstRevision->isMinor() );
		$this->assertNotNull( $firstRevision->getUser() );
		$this->assertSame( 'testprefix>SourceUser1', $firstRevision->getUser()->getName() );
		$this->assertInstanceOf( CommentStoreComment::class, $firstRevision->getComment() );
		$this->assertSame(
			'Original upload comment of Test.png',
			$firstRevision->getComment()->text
		);
		$this->assertSame(
			'Original text of test.jpg',
			$firstRevision->getContent( SlotRecord::MAIN )->serialize()
		);
		$this->assertSame( '20180624133723', $firstRevision->getTimestamp() );
		$tags = ChangeTags::getTags( $this->db, null, $firstRevision->getId() );
		$this->assertSame( [], array_diff( [ 'fileimporter-imported', 'tag1', 'tag2' ], $tags ) );

		// assert import user revision was created correctly
		$article = Article::newFromID( $title->getArticleID() );

		$lastRevision = $article->getPage()->getRevisionRecord();
		$nullRevision = $revisionLookup->getPreviousRevision( $lastRevision );
		$secondRevision = $revisionLookup->getPreviousRevision( $nullRevision );

		$this->assertNotNull( $lastRevision->getUser() );
		$this->assertSame( $targetUser->getName(), $lastRevision->getUser()->getName() );
		$this->assertInstanceOf( CommentStoreComment::class, $lastRevision->getComment() );
		$this->assertSame(
			'User import comment',
			$lastRevision->getComment()->text
		);
		$this->assertSame(
			"<!--imported from http://example.com/Test.png-->\nThis is my text!",
			$lastRevision->getContent( SlotRecord::MAIN )->serialize()
		);

		// assert null revision was created correctly
		$this->assertNotNull( $nullRevision->getUser() );
		$this->assertSame( $targetUser->getName(), $nullRevision->getUser()->getName() );
		$this->assertInstanceOf( CommentStoreComment::class, $nullRevision->getComment() );
		$this->assertSame(
			'imported from http://example.com/Test.png',
			$nullRevision->getComment()->text
		);

		$this->assertNotNull( $secondRevision->getUser() );
		$this->assertSame( 'testprefix>TextChangeUser', $secondRevision->getUser()->getName() );
		$this->assertInstanceOf( CommentStoreComment::class, $secondRevision->getComment() );
		$this->assertSame(
			'I like more text',
			$secondRevision->getComment()->text
		);
		$this->assertSame(
			'This is my text!',
			$secondRevision->getContent( SlotRecord::MAIN )->serialize()
		);
		$tags = ChangeTags::getTags( $this->db, null, $secondRevision->getId() );
		$this->assertSame( [], array_diff( [ 'fileimporter-imported' ], $tags ) );

		// assert import log entry was created correctly
		$this->assertTextRevisionLogEntry( $nullRevision, 'import', 'interwiki', 'fileimporter' );

		// assert upload log entry was created correctly
		// TODO assert tag was added for upload log entry
		$this->assertTextRevisionLogEntry( $nullRevision, 'upload', 'upload' );

		// assert file was imported correctly
		$latestFileRevision = MediaWikiServices::getInstance()->getRepoGroup()->getLocalRepo()
			->newFile( $title );
		$fileHistory = $latestFileRevision->getHistory();
		$this->assertCount( 1, $fileHistory );
		$firstFileRevision = $fileHistory[0];

		// assert latest file revision is correct
		$this->assertSame( self::TITLE, $latestFileRevision->getName() );
		$this->assertSame( 'Changed the file.', $latestFileRevision->getDescription() );
		$this->assertSame( '20180625133723', $latestFileRevision->getTimestamp() );
		$this->assertSame( 3641, $latestFileRevision->getSize() );
		$this->assertSame( 'Imported>FileChangeUser', $latestFileRevision->getUploader()->getName() );
		$this->assertFileLogEntry( $latestFileRevision );

		// assert original file revision is correct
		$this->assertSame( self::TITLE, $firstFileRevision->getName() );
		$this->assertSame( 'Original upload comment of Test.png', $firstFileRevision->getDescription() );
		$this->assertSame( '20180624133723', $firstFileRevision->getTimestamp() );
		$this->assertSame( 3532, $firstFileRevision->getSize() );
		$this->assertSame( 'Imported>SourceUser1', $firstFileRevision->getUploader()->getName() );
		$this->assertFileLogEntry( $firstFileRevision );
	}

	/**
	 * @param RevisionRecord $revision
	 * @param string $type
	 * @param string $expectedSubType
	 * @param string|null $expectedTag
	 */
	private function assertTextRevisionLogEntry(
		RevisionRecord $revision,
		$type,
		$expectedSubType,
		$expectedTag = null
	) {
		$logEntry = $this->getLogType(
			$revision->getPageId(),
			$type,
			$revision->getTimestamp(),
			$expectedTag
		);

		$user = $revision->getUser();
		$this->assertSame( $expectedSubType, $logEntry->getSubtype() );
		$this->assertSame( $revision->getId(), $logEntry->getAssociatedRevId() );
		$this->assertSame(
			$user ? $user->getName() : '',
			$logEntry->getPerformerIdentity()->getName()
		);
		$this->assertSame(
			$user ? $user->getId() : 0,
			$logEntry->getPerformerIdentity()->getId()
		);

		if ( $expectedTag !== null ) {
			$this->assertFileImporterTagWasAdded( $logEntry->getId(), $revision->getId(), $expectedTag );
		}
	}

	/**
	 * @param \File $file
	 */
	private function assertFileLogEntry(
		\File $file
	) {
		$logEntry = $this->getLogType(
			$file->getTitle()->getArticleID(),
			'upload',
			$file->getTimestamp()
		);

		$this->assertSame( 'upload', $logEntry->getSubtype() );
		$this->assertSame( $file->getUploader()->getName(), $logEntry->getPerformerIdentity()->getName() );
		$this->assertSame( $file->getUploader()->getId(), $logEntry->getPerformerIdentity()->getId() );
	}

	/**
	 * @param int $logId
	 * @param int $revId
	 * @param string $expectedTag
	 */
	private function assertFileImporterTagWasAdded( $logId, $revId, $expectedTag ) {
		$this->assertSame( 1, $this->db->selectRowCount(
			[ 'change_tag', 'change_tag_def' ],
			'*',
			[
				'ct_log_id' => $logId,
				'ct_rev_id' => $revId,
				'ctd_name' => $expectedTag,
			],
			__METHOD__,
			[],
			[ 'change_tag_def' => [ 'INNER JOIN', 'ctd_id = ct_tag_id' ] ]
		) );
	}

	/**
	 * @param int $pageId
	 * @param string $type
	 * @param int $timestamp
	 * @param string|null $expectedTag
	 * @return DatabaseLogEntry
	 */
	private function getLogType(
		$pageId,
		$type,
		$timestamp,
		$expectedTag = null
	): DatabaseLogEntry {
		$queryInfo = DatabaseLogEntry::getSelectQueryData();
		$queryInfo['conds'] += [
			'log_page' => $pageId,
			'log_type' => $type,
			'log_timestamp' => $this->db->timestamp( $timestamp ),
		];

		\ChangeTags::modifyDisplayQuery(
			$queryInfo['tables'],
			$queryInfo['fields'],
			$queryInfo['conds'],
			$queryInfo['join_conds'],
			$queryInfo['options']
		);

		$row = $this->db->selectRow(
			$queryInfo['tables'],
			$queryInfo['fields'],
			$queryInfo['conds'],
			__METHOD__,
			$queryInfo['options'],
			$queryInfo['join_conds']
		);

		if ( $expectedTag !== null ) {
			$this->assertContains(
				$expectedTag,
				explode( ',', $row->ts_tags ),
				$expectedTag . ' tag was added' );
		}
		return DatabaseLogEntry::newFromRow( $row );
	}

	private function newImporter(): Importer {
		$services = MediaWikiServices::getInstance();

		$user = UserIdentityValue::newExternal( 'Imported', 'SourceUser1' );
		$userLookup = $this->createMock( UserIdentityLookup::class );
		$userLookup->method( 'getUserIdentityByName' )->willReturn( $user );

		$uploadRevisionImporter = new ImportableUploadRevisionImporter(
			true,
			new NullLogger()
		);
		$uploadRevisionImporter->setNullRevisionCreation( false );

		$oldRevisionImporter = new ImportableOldRevisionImporter(
			true,
			new NullLogger(),
			$services->getDBLoadBalancer(),
			$services->getRevisionStore(),
			$services->getSlotRoleRegistry(),
			$services->getWikiPageFactory()
		);

		return new Importer(
			$services->getWikiPageFactory(),
			$this->newWikiRevisionFactory(),
			$services->getService( 'FileImporterNullRevisionCreator' ),
			$userLookup,
			$this->newHttpRequestExecutor(),
			$services->getService( 'FileImporterUploadBaseFactory' ),
			$oldRevisionImporter,
			$uploadRevisionImporter,
			new FileTextRevisionValidator(),
			$services->getRestrictionStore()
		);
	}

	private function newWikiRevisionFactory(): WikiRevisionFactory {
		$mock = $this->getMockBuilder( WikiRevisionFactory::class )
			->onlyMethods( [ 'newFromFileRevision' ] )
			->getMock();
		$mock->method( 'newFromFileRevision' )
			->willReturnCallback(
				function ( FileRevision $fileRevision, $src ) {
					$realFactory = new WikiRevisionFactory();

					$tempFile = $this->getNewTempFile();
					$srcFile = $fileRevision->getField( '_test_file_src' );
					// the file will be moved or deleted in the process so create a copy
					copy( $srcFile, $tempFile );

					return $realFactory->newFromFileRevision( $fileRevision, $tempFile );
				}
			);
		return $mock;
	}

	private function newHttpRequestExecutor(): HttpRequestExecutor {
		$mock = $this->createMock( HttpRequestExecutor::class );
		$mock->method( 'executeAndSave' )->willReturn( true );
		return $mock;
	}

	private function newImportPlan(): ImportPlan {
		$sourceUrl = new SourceUrl( 'http://example.com/Test.png' );
		$sourceLinkTarget = new TitleValue( NS_FILE, self::TITLE );

		$textRevisions = $this->newTextRevisions();
		$fileRevisions = $this->newFileRevisions();

		$importDetails = new ImportDetails(
			$sourceUrl,
			$sourceLinkTarget,
			$textRevisions,
			$fileRevisions
		);

		$config = MediaWikiServices::getInstance()->getMainConfig();

		$message = $this->createMock( Message::class );
		$message->expects( $this->exactly( 2 ) )
			->method( 'inContentLanguage' )
			->willReturn( $message );
		$message->expects( $this->exactly( 2 ) )
			->method( 'plain' )
			->willReturn( '' );

		$messageLocalizer = $this->createMock( MessageLocalizer::class );
		$messageLocalizer->expects( $this->exactly( 2 ) )
			->method( 'msg' )
			->with( 'fileimporter-post-import-revision-annotation' )
			->willReturn( $message );

		return new ImportPlan(
			new ImportRequest(
				'http://example.com/Test.png',
				null,
				null,
				'User import comment'
			),
			$importDetails,
			$config,
			$messageLocalizer,
			'testprefix'
		);
	}

	private function newTextRevisions(): TextRevisions {
		return new TextRevisions( [
			new TextRevision( [
				'minor' => '',
				'user' => 'TextChangeUser',
				'timestamp' => '2018-06-27T13:37:23Z',
				'sha1' => '',
				'contentmodel' => 'wikitext',
				'contentformat' => 'text/x-wiki',
				'comment' => 'I like more text',
				'*' => 'This is my text!',
				'title' => 'test.jpg',
				'tags' => [],
			] ),
			new TextRevision( [
				'minor' => '',
				'user' => 'SourceUser1',
				'timestamp' => '2018-06-24T13:37:23Z',
				'sha1' => '',
				'contentmodel' => 'wikitext',
				'contentformat' => 'text/x-wiki',
				'comment' => 'Original upload comment of Test.png',
				'*' => 'Original text of test.jpg',
				'title' => 'test.jpg',
				'tags' => [ 'tag1', 'tag2' ],
			] ),
		] );
	}

	private function newFileRevisions(): FileRevisions {
		return new FileRevisions( [
			new FileRevision( [
				'name' => 'File:test.jpg',
				'description' => 'Changed the file.',
				'user' => 'FileChangeUser',
				'timestamp' => '2018-06-25T13:37:23Z',
				'sha1' => \FSFile::getSha1Base36FromPath( self::TEST_FILE2_SRC ),
				'size' => '3532',
				'thumburl' => '',
				'url' => 'http://example.com/Test.png',
				'_test_file_src' => self::TEST_FILE2_SRC,
			] ),
			new FileRevision( [
				'name' => 'File:test.jpg',
				'description' => 'Original upload comment of Test.png',
				'user' => 'SourceUser1',
				'timestamp' => '2018-06-24T13:37:23Z',
				'sha1' => \FSFile::getSha1Base36FromPath( self::TEST_FILE_SRC ),
				'size' => '7317',
				'thumburl' => '',
				'url' => 'http://example.com/Test.png',
				'_test_file_src' => self::TEST_FILE_SRC,
			] ),
		] );
	}

}
