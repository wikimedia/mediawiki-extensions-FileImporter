<?php

namespace FileImporter\Tests\Services;

use Article;
use Config;
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
use FileImporter\Services\WikiPageFactory;
use FileImporter\Services\WikiRevisionFactory;
use ImportableOldRevisionImporter;
use ImportableUploadRevisionImporter;
use MediaWiki\MediaWikiServices;
use Psr\Log\NullLogger;
use TitleValue;
use User;

/**
 * @covers \FileImporter\Services\Importer
 *
 * @group Database
 *
 * @license GPL-2.0-or-later
 * @author Christoph Jauera <christoph.jauera@wikimedia.de>
 */
class ImporterTest extends \MediaWikiTestCase {

	const TEST_FILE_SRC = __DIR__ . '/../res/testfile.png';
	const TEST_FILE2_SRC = __DIR__ . '/../res/testfile2.png';
	// Random number (actually the SHA1 of this file) to not conflict with other tests
	const TITLE = 'Test-29e7a6ff58c5eb980fc0642a13b59cb9c5a3cf55.png';

	/**
	 * @var Config
	 */
	private $config;

	/**
	 * @var User
	 */
	private $targetUser;

	protected function setUp() {
		parent::setUp();

		$this->setMwGlobals( [
			'wgHooks' => [],
			'wgFileImporterCommentForPostImportRevision' => 'imported from $1',
			'wgFileImporterTextForPostImportRevision' => "imported from $1\n",
		] );

		$this->config = new \HashConfig( [ 'EnableUploads' => true ] );
		$this->targetUser = $this->getTestUser()->getUser();
	}

	public static function tearDownAfterClass() {
		parent::tearDownAfterClass();

		// avoid file leftovers when repeatedly run on a local system
		$file = MediaWikiServices::getInstance()->getRepoGroup()->getLocalRepo()
			->newFile( self::TITLE );
		if ( $file->exists() ) {
			$file->delete( 'This was just from a PHPUnit test.' );
		}
	}

	public function testImport() {
		$importer = $this->newImporter();
		$plan = $this->newImportPlan();
		$title = $plan->getTitle();

		$this->assertFalse( $title->exists() );

		$importer->import(
			$this->targetUser,
			$plan
		);

		// assert page was locally created
		$this->assertTrue( $title->exists() );
		$this->assertTrue( $title->isWikitextPage() );

		// assert original revision was imported correctly
		$firstRevision = $title->getFirstRevision();

		$this->assertFalse( $firstRevision->isMinor() );
		$this->assertSame( 'testprefix>SourceUser1', $firstRevision->getUserText() );
		$this->assertSame( 'Original upload comment of Test.png', $firstRevision->getComment() );
		$this->assertSame(
			'Original text of test.jpg',
			$firstRevision->getContent()->serialize()
		);
		$this->assertSame( '20180624133723', $firstRevision->getTimestamp() );

		// assert import user revision was created correctly
		$article = Article::newFromID( $title->getArticleID() );

		$lastRevision = $article->getRevision();
		$nullRevision = $lastRevision->getPrevious();
		$secondRevision = $nullRevision->getPrevious();

		$this->assertSame( $this->targetUser->getName(), $lastRevision->getUserText() );
		$this->assertSame( 'User import comment', $lastRevision->getComment() );
		$this->assertSame(
			"imported from http://example.com/Test.png\nThis is my text!",
			$lastRevision->getContent()->serialize()
		);

		// assert null revision was created correctly
		$this->assertSame( $this->targetUser->getName(), $nullRevision->getUserText() );
		$this->assertSame(
			'imported from http://example.com/Test.png',
			$nullRevision->getComment()
		);

		$this->assertSame( 'testprefix>TextChangeUser', $secondRevision->getUserText() );
		$this->assertSame( 'I like more text', $secondRevision->getComment() );
		$this->assertSame(
			'This is my text!',
			$secondRevision->getContent()->serialize()
		);

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
		$this->assertSame( 'Imported>FileChangeUser', $latestFileRevision->getUser() );
		$this->assertFileLogEntry( $latestFileRevision );

		// assert original file revision is correct
		$this->assertSame( self::TITLE, $firstFileRevision->getName() );
		$this->assertSame( 'Original upload comment of Test.png', $firstFileRevision->getDescription() );
		$this->assertSame( '20180624133723', $firstFileRevision->getTimestamp() );
		$this->assertSame( 3532, $firstFileRevision->getSize() );
		$this->assertSame( 'Imported>SourceUser1', $firstFileRevision->getUser() );
		$this->assertFileLogEntry( $firstFileRevision );
	}

	/**
	 * @param \Revision $revision
	 * @param string $type
	 * @param string $expectedSubType
	 * @param string|null $expectedTag
	 */
	private function assertTextRevisionLogEntry(
		\Revision $revision,
		$type,
		$expectedSubType,
		$expectedTag = null
	) {
		$logEntry = $this->getLogType(
			$revision->getTitle()->getArticleID(),
			$type,
			$revision->getTimestamp(),
			$expectedTag
		);

		$this->assertSame( $expectedSubType, $logEntry->getSubtype() );
		$this->assertSame( $revision->getId(), $logEntry->getAssociatedRevId() );
		$this->assertSame( $revision->getUserText(), $logEntry->getPerformer()->getName() );
		$this->assertSame( $revision->getUser(), $logEntry->getPerformer()->getId() );

		if ( $expectedTag !== null ) {
			$this->assertFileImporterTagWasAdded( $logEntry->getId(), $revision->getId() );
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
		$this->assertSame( $file->getUser(), $logEntry->getPerformer()->getName() );
		$this->assertSame( $file->getUser( 'id' ), $logEntry->getPerformer()->getId() );
	}

	/**
	 * @param int $logId
	 * @param int $revId
	 */
	private function assertFileImporterTagWasAdded( $logId, $revId ) {
		$this->assertSame( 1, $this->db->selectRowCount(
			[ 'change_tag', 'change_tag_def' ],
			'*',
			[
				'ct_log_id' => $logId,
				'ct_rev_id' => $revId,
				'ctd_name' => 'fileimporter',
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
	private function getLogType( $pageId, $type, $timestamp, $expectedTag = null ) {
		$queryInfo = DatabaseLogEntry::getSelectQueryData();
		$queryInfo['conds'] += [
			'log_page' => $pageId,
			'log_type' => $type,
			'log_timestamp' => $timestamp,
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
			$this->assertSame( $expectedTag, $row->ts_tags, $expectedTag . ' tag was added' );
		}
		return DatabaseLogEntry::newFromRow( $row );
	}

	/**
	 * @return Importer
	 */
	private function newImporter() {
		$services = MediaWikiServices::getInstance();
		$logger = new NullLogger();

		$uploadRevisionImporter = new ImportableUploadRevisionImporter(
			true,
			$logger
		);
		$uploadRevisionImporter->setNullRevisionCreation( false );

		$oldRevisionImporter = new ImportableOldRevisionImporter(
			true,
			$logger,
			$services->getDBLoadBalancer()
		);

		return new Importer(
			new WikiPageFactory(),
			$this->newWikiRevisionFactory( $this->config ),
			$services->getService( 'FileImporterNullRevisionCreator' ),
			$this->newHttpRequestExecutor(),
			$services->getService( 'FileImporterUploadBaseFactory' ),
			$oldRevisionImporter,
			$uploadRevisionImporter,
			new FileTextRevisionValidator(),
			new \NullStatsdDataFactory(),
			$logger
		);
	}

	/**
	 * @param Config $config
	 * @return WikiRevisionFactory
	 */
	private function newWikiRevisionFactory( Config $config ) {
		$mock = $this->getMockBuilder( WikiRevisionFactory::class )
			->setConstructorArgs( [ $config ] )
			->setMethods( [ 'newFromFileRevision' ] )
			->getMock();
		$mock->method( 'newFromFileRevision' )
			->will( $this->returnCallback(
				function ( FileRevision $fileRevision, $src ) {
					$realFactory = new WikiRevisionFactory( $this->config );

					$tempFile = $this->getNewTempFile();
					$srcFile = $fileRevision->getFields()['_test_file_src'];
					// the file will be moved or deleted in the process so create a copy
					copy( $srcFile, $tempFile );

					return $realFactory->newFromFileRevision( $fileRevision, $tempFile );
				}
			) );
		return $mock;
	}

	/**
	 * @return HttpRequestExecutor
	 */
	private function newHttpRequestExecutor() {
		$mock = $this->createMock( HttpRequestExecutor::class );
		$mock->method( 'executeAndSave' )->willReturn( true );
		return $mock;
	}

	/**
	 * @return ImportPlan
	 */
	private function newImportPlan() {
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

		return new ImportPlan(
			new ImportRequest(
				'http://example.com/Test.png',
				null,
				null,
				'User import comment'
			),
			$importDetails,
			'testprefix'
		);
	}

	/**
	 * @return TextRevisions
	 */
	private function newTextRevisions() {
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
			] ),
		] );
	}

	/**
	 * @return FileRevisions
	 */
	private function newFileRevisions() {
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
