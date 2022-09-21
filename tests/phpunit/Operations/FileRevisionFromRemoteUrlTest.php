<?php

namespace FileImporter\Tests\Operations;

use FileImporter\Data\FileRevision;
use FileImporter\Data\TextRevision;
use FileImporter\Operations\FileRevisionFromRemoteUrl;
use FileImporter\Services\Http\HttpRequestExecutor;
use FileImporter\Services\UploadBase\UploadBaseFactory;
use FileImporter\Services\WikiRevisionFactory;
use ImportableUploadRevisionImporter;
use MediaWiki\MediaWikiServices;
use MediaWiki\Permissions\RestrictionStore;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\User\UserIdentityLookup;
use MediaWiki\User\UserIdentityValue;
use Psr\Log\NullLogger;
use Title;

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

	public function provideTestNewFileRevisionFromRemoteUrl() {
		return [
			[ null ],
			[ $this->newTextRevision() ],
		];
	}

	/**
	 * @dataProvider provideTestNewFileRevisionFromRemoteUrl
	 */
	public function testPrepareWithBrokenUrl( TextRevision $textRevision = null ) {
		$fileRevisionFromRemoteUrl = new FileRevisionFromRemoteUrl(
			Title::newFromText( __METHOD__ ),
			$this->getTestUser()->getUser(),
			$this->newFileRevision( 'NULL' ),
			$textRevision,
			$this->createMock( UserIdentityLookup::class ),
			$this->createMock( HttpRequestExecutor::class ),
			$this->createMock( WikiRevisionFactory::class ),
			$this->createMock( UploadBaseFactory::class ),
			$this->createMock( ImportableUploadRevisionImporter::class ),
			$this->createMock( RestrictionStore::class )
		);

		$this->assertFalse( $fileRevisionFromRemoteUrl->prepare()->isOK() );
	}

	public function testPrepare() {
		$title = Title::newFromText( self::TITLE, NS_FILE );
		$fileRevisionFromRemoteUrl = $this->newFileRevisionFromRemoteUrl( $title );

		$this->assertNull( $fileRevisionFromRemoteUrl->getWikiRevision() );

		$status = $fileRevisionFromRemoteUrl->prepare();
		$wikiRevision = $fileRevisionFromRemoteUrl->getWikiRevision();

		$this->assertTrue( $status->isOK() );
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
		$title = Title::newFromText( self::TITLE, NS_FILE );
		$fileRevisionFromRemoteUrl = $this->newFileRevisionFromRemoteUrl( $title );

		$this->assertTrue( $fileRevisionFromRemoteUrl->prepare()->isOK() );
		$this->assertFalse( $title->exists() );
		$this->assertTrue( $fileRevisionFromRemoteUrl->validate()->isOK() );
	}

	public function testCommit() {
		$title = Title::newFromText( self::TITLE, NS_FILE );
		$fileRevisionFromRemoteUrl = $this->newFileRevisionFromRemoteUrl( $title );

		$this->assertTrue( $fileRevisionFromRemoteUrl->prepare()->isOK() );
		$this->assertTrue( $fileRevisionFromRemoteUrl->validate()->isOK() );
		$status = $fileRevisionFromRemoteUrl->commit();

		$this->assertTrue( $status->isOK() );
		$this->assertTrue( $title->exists() );

		// there will be a text revision created with the upload
		$firstRevision = MediaWikiServices::getInstance()
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
		$file = MediaWikiServices::getInstance()->getRepoGroup()->findFile( $title );
		$this->assertTrue( $file !== false );
		$this->assertSame( self::TITLE, $file->getName() );
		$this->assertSame( 'Original upload comment of Test.png', $file->getDescription() );
		$this->assertSame( 'Imported>SourceUser1', $file->getUploader()->getName() );
		$this->assertSame( '20180624133723', $file->getTimestamp() );
		$this->assertSame( 'image/png', $file->getMimeType() );
		$this->assertSame( 3532, $file->getSize() );
		$this->assertSame( \FSFile::getSha1Base36FromPath( self::TEST_FILE_SRC ), $file->getSha1() );
	}

	private function newFileRevisionFromRemoteUrl( Title $title ) {
		$services = MediaWikiServices::getInstance();

		$userLookup = $this->createMock( UserIdentityLookup::class );
		$user = UserIdentityValue::newExternal( 'Imported', 'SourceUser1' );
		$userLookup->method( 'getUserIdentityByName' )->willReturn( $user );

		$fileRevisionFromRemoteUrl = new FileRevisionFromRemoteUrl(
			$title,
			$this->getTestUser()->getUser(),
			$this->newFileRevision( 'http://example.com/Test.png' ),
			$this->newTextRevision(),
			$userLookup,
			$this->newHttpRequestExecutor(),
			$this->newWikiRevisionFactory(),
			$services->getService( 'FileImporterUploadBaseFactory' ),
			$this->newUploadRevisionImporter(),
			$services->getRestrictionStore()
		);

		return $fileRevisionFromRemoteUrl;
	}

	private function newHttpRequestExecutor(): HttpRequestExecutor {
		$mock = $this->createMock( HttpRequestExecutor::class );
		$mock->method( 'executeAndSave' )->willReturn( true );
		return $mock;
	}

	private function newWikiRevisionFactory(): WikiRevisionFactory {
		$mock = $this->createMock( WikiRevisionFactory::class );
		$mock->method( 'newFromFileRevision' )
			->willReturnCallback(
				function ( FileRevision $fileRevision, $src ) {
					$realFactory = new WikiRevisionFactory();

					$tempFile = $this->getNewTempFile();
					// the file will be moved or deleted in the process so create a copy
					copy( self::TEST_FILE_SRC, $tempFile );

					return $realFactory->newFromFileRevision( $fileRevision, $tempFile );
				}
			);
		return $mock;
	}

	/**
	 * @param string $url
	 * @return FileRevision
	 */
	private function newFileRevision( $url ): FileRevision {
		return new FileRevision( [
			'name' => 'File:test.jpg',
			'description' => 'Original upload comment of Test.png',
			'user' => 'SourceUser1',
			'timestamp' => '2018-06-24T13:37:23Z',
			'sha1' => \FSFile::getSha1Base36FromPath( self::TEST_FILE_SRC ),
			'size' => '12345',
			'thumburl' => 'http://example.com/thumb/Test.png',
			'url' => $url,
		] );
	}

	private function newTextRevision(): TextRevision {
		return new TextRevision( [
			'minor' => '',
			'user' => '',
			'timestamp' => '',
			'sha1' => '',
			'contentmodel' => '',
			'contentformat' => '',
			'comment' => '',
			'*' => '',
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
