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
			'wgFileImporterCommentForPostImportRevision' => 'imported from $1',
			'wgFileImporterTextForPostImportRevision' => "imported from $1\n",
		] );

		$this->config = new \HashConfig( [ 'EnableUploads' => true ] );
		$this->targetUser = $this->getTestUser()->getUser();
	}

	public function testImport() {
		$importer = $this->newImporter();
		$plan = $this->newImportPlan();
		$title = $plan->getTitle();

		$this->assertFalse( $title->exists() );

		$result = $importer->import(
			$this->targetUser,
			$plan
		);

		// assert page was locally created
		$this->assertTrue( $result );
		$this->assertTrue( $title->exists() );
		$this->assertTrue( $title->isWikitextPage() );

		// assert original revision was imported correctly
		$firstRevision = $title->getFirstRevision();

		$this->assertFalse( $firstRevision->isMinor() );
		$this->assertSame( 'testprefix>SourceUser1', $firstRevision->getUserText() );
		$this->assertSame( 'Original upload comment of Test.png', $firstRevision->getComment() );
		$this->assertSame(
			'Original text of test.jpg',
			$firstRevision->getContent()->serialize( $firstRevision->getContentFormat() )
		);
		$this->assertSame( '20180624133723', $firstRevision->getTimestamp() );

		// assert import user revision was created correctly
		$article = Article::newFromID( $title->getArticleID() );
		$lastRevision = $article->getRevision();

		$this->assertSame( $this->targetUser->getName(), $lastRevision->getUserText() );
		$this->assertSame( 'User import comment', $lastRevision->getComment() );
		$this->assertSame(
			"imported from http://example.com/Test.png\nOriginal text of test.jpg",
			$lastRevision->getContent()->serialize( $lastRevision->getContentFormat() )
		);

		// assert null revision was created correctly
		$nullRevision = $lastRevision->getPrevious();
		$this->assertSame( $this->targetUser->getName(), $nullRevision->getUserText() );
		$this->assertSame(
			'imported from http://example.com/Test.png',
			$nullRevision->getComment()
		);

		// assert import log entry was created correctly
		$logEntry = $this->getLogTypeFromPageId( $title->getArticleID(), 'import' );
		$this->assertSame( 'interwiki', $logEntry->getSubtype() );
		$this->assertSame( $this->targetUser->getName(), $logEntry->getPerformer()->getName() );
		$this->assertSame( $this->targetUser->getId(), $logEntry->getPerformer()->getId() );
		$this->assertSame( $nullRevision->getId(), $logEntry->getAssociatedRevId() );
		$this->assertFileImporterTagWasAdded( $logEntry->getId(), $nullRevision->getId() );

		// assert file was imported correctly
		$file = wfFindFile( $title );
		$this->assertTrue( $file !== false );
		$this->assertSame( self::TITLE, $file->getName() );
		$this->assertSame( 'Original upload comment of Test.png', $file->getDescription() );
		$this->assertSame( 'SourceUser1', $file->getUser() );
		$this->assertSame( '20180624133723', $file->getTimestamp() );
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
	 * @return DatabaseLogEntry
	 */
	private function getLogTypeFromPageId( $pageId, $type ) {
		$queryInfo = DatabaseLogEntry::getSelectQueryData();
		$queryInfo['conds'] += [
			'log_page' => $pageId,
			'log_type' => $type,
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

		$this->assertSame( 'fileimporter', $row->ts_tags, 'fileimporter tag was added' );
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
					// the file will be moved or deleted in the process so create a copy
					copy( self::TEST_FILE_SRC, $tempFile );

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
				'user' => 'SourceUser1',
				'timestamp' => '2018-06-24T13:37:23Z',
				'sha1' => 'TextSHA1',
				'contentmodel' => 'wikitext',
				'contentformat' => 'text/x-wiki',
				'comment' => 'Original upload comment of Test.png',
				'*' => 'Original text of test.jpg',
				'title' => 'test.jpg',
			] )
		] );
	}

	/**
	 * @return FileRevisions
	 */
	private function newFileRevisions() {
		return new FileRevisions( [
			new FileRevision( [
				'name' => 'File:test.jpg',
				'description' => 'Original upload comment of Test.png',
				'user' => 'SourceUser1',
				'timestamp' => '2018-06-24T13:37:23Z',
				'sha1' => \FSFile::getSha1Base36FromPath( self::TEST_FILE_SRC ),
				'size' => '12345',
				'thumburl' => 'http://example.com/thumb/Test.png',
				'url' => 'http://example.com/Test.png',
			] )
		] );
	}

}
