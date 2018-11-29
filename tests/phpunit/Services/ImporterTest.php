<?php

namespace FileImporter\Tests\Services;

use Article;
use Config;
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
		$firstRevison = $title->getFirstRevision();

		$this->assertFalse( $firstRevison->isMinor() );
		$this->assertSame( 'testprefix>SourceUser1', $firstRevison->getUserText() );
		$this->assertSame( 'Original upload comment of Test.png', $firstRevison->getComment() );
		$this->assertSame(
			'Original text of test.jpg',
			$firstRevison->getContent()->serialize( $firstRevison->getContentFormat() )
		);
		$this->assertSame( '20180624133723', $firstRevison->getTimestamp() );

		// assert import user revision was created correctly
		$article = Article::newFromID( $title->getArticleID() );
		$lastRevison = $article->getRevision();

		$this->assertSame( $this->targetUser->getName(), $lastRevison->getUserText() );
		$this->assertSame( 'User import comment', $lastRevison->getComment() );
		$this->assertSame(
			"imported from http://example.com/Test.png\nOriginal text of test.jpg",
			$lastRevison->getContent()->serialize( $lastRevison->getContentFormat() )
		);

		// assert null revision was created correctly
		$nullRevision = $lastRevison->getPrevious();
		$this->assertSame( $this->targetUser->getName(), $nullRevision->getUserText() );
		$this->assertSame(
			'imported from http://example.com/Test.png',
			$nullRevision->getComment()
		);

		// assert file was imported correctly
		$file = wfFindFile( $title );
		$this->assertTrue( $file !== false );
		$this->assertSame( self::TITLE, $file->getName() );
		$this->assertSame( 'Original upload comment of Test.png', $file->getDescription() );
		$this->assertSame( 'SourceUser1', $file->getUser() );
		$this->assertSame( '20180624133723', $file->getTimestamp() );
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
