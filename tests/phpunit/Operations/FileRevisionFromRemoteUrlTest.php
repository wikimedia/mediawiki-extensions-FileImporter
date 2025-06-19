<?php

namespace FileImporter\Tests\Operations;

use FileImporter\Data\FileRevision;
use FileImporter\Data\TextRevision;
use FileImporter\Operations\FileRevisionFromRemoteUrl;
use FileImporter\Services\Http\HttpRequestExecutor;
use FileImporter\Services\UploadBase\UploadBaseFactory;
use FileImporter\Services\WikiRevisionFactory;
use ImportableUploadRevisionImporter;
use MediaWiki\Content\IContentHandlerFactory;
use MediaWiki\FileRepo\File\File;
use MediaWiki\Permissions\RestrictionStore;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Title\Title;
use MediaWiki\User\UserIdentityLookup;
use MediaWiki\User\UserIdentityValue;
use Psr\Log\NullLogger;
use Wikimedia\FileBackend\FSFile\FSFile;
use WikiRevision;

/**
 * @covers \FileImporter\Operations\FileRevisionFromRemoteUrl
 *
 * @group Database
 *
 * @license GPL-2.0-or-later
 * @author Christoph Jauera <christoph.jauera@wikimedia.de>
 */
class FileRevisionFromRemoteUrlTest extends \MediaWikiIntegrationTestCase {

	private const TEST_FILE_SRC = __DIR__ . '/../res/testfile.png';
	private const TITLE = 'Test-29e7a6ff58c5eb980fc0642a13b59csb9c5a3cf55.png';

	public static function provideTestNewFileRevisionFromRemoteUrl() {
		return [
			[ null ],
			[ self::newTextRevision() ],
		];
	}

	/**
	 * @dataProvider provideTestNewFileRevisionFromRemoteUrl
	 */
	public function testPrepareWithBrokenUrl( ?TextRevision $textRevision = null ) {
		$fileRevisionFromRemoteUrl = new FileRevisionFromRemoteUrl(
			Title::makeTitle( NS_MAIN, __METHOD__ ),
			$this->getTestUser()->getUser(),
			$this->newFileRevision( 'NULL' ),
			$textRevision,
			$this->createNoOpMock( UserIdentityLookup::class ),
			$this->createNoOpMock( HttpRequestExecutor::class ),
			$this->createNoOpMock( WikiRevisionFactory::class ),
			$this->createNoOpMock( UploadBaseFactory::class ),
			$this->createNoOpMock( ImportableUploadRevisionImporter::class ),
			$this->createNoOpMock( RestrictionStore::class )
		);

		$this->assertFalse( $fileRevisionFromRemoteUrl->prepare()->isOK() );
	}

	public function testPrepare() {
		$title = Title::makeTitle( NS_FILE, self::TITLE );
		$fileRevisionFromRemoteUrl = $this->newFileRevisionFromRemoteUrl( $title );

		$this->assertNull( $fileRevisionFromRemoteUrl->getWikiRevision() );

		$status = $fileRevisionFromRemoteUrl->prepare();
		$wikiRevision = $fileRevisionFromRemoteUrl->getWikiRevision();

		$this->assertStatusGood( $status );
		$this->assertFalse( $title->exists() );
		$this->assertTrue( $title->isWikitextPage() );
		$this->assertSame( 0, $wikiRevision->getID() );
		$this->assertSame( $title, $wikiRevision->getTitle() );
		$this->assertSame( 'Imported>SourceUser1', $wikiRevision->getUser() );
		$this->assertSame( '', $wikiRevision->getText() );
		$this->assertSame( 'Original upload comment of Test.png', $wikiRevision->getComment() );
		$this->assertSame( '20180624133723', $wikiRevision->getTimestamp() );
		$this->assertSame( [], $wikiRevision->getSlotRoles() );
		$this->assertFalse( $wikiRevision->getMinor() );
	}

	public function testValidate() {
		$title = Title::makeTitle( NS_FILE, self::TITLE );
		$fileRevisionFromRemoteUrl = $this->newFileRevisionFromRemoteUrl( $title );

		$this->assertTrue( $fileRevisionFromRemoteUrl->prepare()->isOK() );
		$this->assertFalse( $title->exists() );
		$this->assertTrue( $fileRevisionFromRemoteUrl->validate()->isOK() );
	}

	public function testCommit() {
		$title = Title::makeTitle( NS_FILE, self::TITLE );
		$fileRevisionFromRemoteUrl = $this->newFileRevisionFromRemoteUrl( $title );

		$this->assertTrue( $fileRevisionFromRemoteUrl->prepare()->isOK() );
		$this->assertTrue( $fileRevisionFromRemoteUrl->validate()->isOK() );
		$status = $fileRevisionFromRemoteUrl->commit();

		$this->assertStatusGood( $status );
		$this->assertTrue( $title->exists() );

		// there will be a text revision created with the upload
		$firstRevision = $this->getServiceContainer()
			->getRevisionLookup()
			->getFirstRevision( $title );
		$content = $firstRevision->getContent( SlotRecord::MAIN );
		$this->assertNotNull( $firstRevision->getUser() );
		$this->assertSame( 'Imported>SourceUser1', $firstRevision->getUser()->getName() );
		$this->assertSame( CONTENT_MODEL_WIKITEXT, $content->getModel() );
		$this->assertSame( 'Original upload comment of Test.png', $content->getTextForSummary() );
		$this->assertNotNull( $firstRevision->getComment() );
		$this->assertSame(
			'Original upload comment of Test.png',
			$firstRevision->getComment()->text
		);
		// title will be created from scratch and will have a current timestamp
		$this->assertTrue( $firstRevision->getTimestamp() > 20180624133723 );
		$this->assertFalse( $firstRevision->isMinor() );

		// assert file was imported correctly
		$file = $this->getServiceContainer()->getRepoGroup()->findFile( $title );
		$this->assertInstanceOf( File::class, $file );
		$this->assertSame( self::TITLE, $file->getName() );
		$this->assertSame( 'Original upload comment of Test.png', $file->getDescription() );
		$this->assertSame( 'Imported>SourceUser1', $file->getUploader()->getName() );
		$this->assertSame( '20180624133723', $file->getTimestamp() );
		$this->assertSame( 'image/png', $file->getMimeType() );
		$this->assertSame( 3532, $file->getSize() );
		$this->assertSame( FSFile::getSha1Base36FromPath( self::TEST_FILE_SRC ), $file->getSha1() );
	}

	private function newFileRevisionFromRemoteUrl( Title $title ) {
		$services = $this->getServiceContainer();

		$userLookup = $this->createMock( UserIdentityLookup::class );
		$user = UserIdentityValue::newExternal( 'Imported', 'SourceUser1' );
		$userLookup->method( 'getUserIdentityByName' )->willReturn( $user );

		$fileRevisionFromRemoteUrl = new FileRevisionFromRemoteUrl(
			$title,
			$this->getTestUser()->getUser(),
			$this->newFileRevision( 'http://example.com/Test.png' ),
			self::newTextRevision(),
			$userLookup,
			$this->createMock( HttpRequestExecutor::class ),
			$this->newWikiRevisionFactory(),
			$services->getService( 'FileImporterUploadBaseFactory' ),
			$this->newUploadRevisionImporter(),
			$this->createNoOpMock( RestrictionStore::class, [ 'isProtected' ] )
		);

		return $fileRevisionFromRemoteUrl;
	}

	private function newWikiRevisionFactory(): WikiRevisionFactory {
		$mock = $this->createMock( WikiRevisionFactory::class );
		$mock->method( 'newFromFileRevision' )
			->willReturnCallback(
				function ( FileRevision $fileRevision, string $src ): WikiRevision {
					$realFactory = new WikiRevisionFactory( $this->createNoOpMock( IContentHandlerFactory::class ) );

					$tempFile = $this->getNewTempFile();
					// the file will be moved or deleted in the process so create a copy
					copy( self::TEST_FILE_SRC, $tempFile );

					return $realFactory->newFromFileRevision( $fileRevision, $tempFile );
				}
			);
		return $mock;
	}

	private function newFileRevision( string $url ): FileRevision {
		return new FileRevision( [
			'name' => 'File:test.jpg',
			'description' => 'Original upload comment of Test.png',
			'user' => 'SourceUser1',
			'timestamp' => '2018-06-24T13:37:23Z',
			'sha1' => FSFile::getSha1Base36FromPath( self::TEST_FILE_SRC ),
			'size' => '12345',
			'thumburl' => '//example.com/thumb/Test.png',
			'url' => $url,
		] );
	}

	private static function newTextRevision(): TextRevision {
		return new TextRevision( [
			'minor' => '',
			'user' => '',
			'timestamp' => '',
			'sha1' => '',
			'comment' => '',
			'slots' => [
				SlotRecord::MAIN => [
					'contentmodel' => '',
					'contentformat' => '',
					'content' => '',
				]
			],
			'title' => '',
			'tags' => [],
		] );
	}

	private function newUploadRevisionImporter(): ImportableUploadRevisionImporter {
		return new ImportableUploadRevisionImporter(
			true,
			new NullLogger()
		);
	}

}
