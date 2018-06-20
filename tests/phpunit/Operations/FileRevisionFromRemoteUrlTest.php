<?php

namespace FileImporter\Operations\Test;

use FileImporter\Data\FileRevision;
use FileImporter\Data\TextRevision;
use FileImporter\Operations\FileRevisionFromRemoteUrl;
use FileImporter\Services\Http\HttpRequestExecutor;
use FileImporter\Services\UploadBase\UploadBaseFactory;
use FileImporter\Services\WikiRevisionFactory;
use ImportableUploadRevisionImporter;
use Psr\Log\NullLogger;
use MediaWikiTestCase;
use PHPUnit4And6Compat;
use Title;
use User;

/**
 * @covers \FileImporter\Operations\FileRevisionFromRemoteUrl
 *
 * @license GPL-2.0-or-later
 * @author Christoph Jauera <christoph.jauera@wikimedia.de>
 */
class FileRevisionFromRemoteUrlTest extends MediaWikiTestCase {
	use PHPUnit4And6Compat;

	public function provideTestNewFileRevisionFromRemoteUrl() {
		return [
			[ null ],
			[ $this->createMock( TextRevision::class ) ],
		];
	}

	/**
	 * @dataProvider provideTestNewFileRevisionFromRemoteUrl
	 */
	public function testNewFileRevisionFromRemoteUrl( TextRevision $textRevision = null ) {
		new FileRevisionFromRemoteUrl(
			Title::newFromText( 'Test' ),
			User::newFromName( 'TestUser' ),
			$this->newMockFileRevision(),
			$textRevision,
			$this->createMock( HttpRequestExecutor::class ),
			$this->createMock( WikiRevisionFactory::class ),
			$this->createMock( UploadBaseFactory::class ),
			$this->newUploadRevisionImporter()
		);

		$this->addToAssertionCount( 1 );
	}

	public function testFileRevisionFromRemoteUrlPrepareWithBrokenUrl() {
		$fileRevisionFromRemoteUrl = new FileRevisionFromRemoteUrl(
			Title::newFromText( 'Test' ),
			User::newFromName( 'TestUser' ),
			$this->newMockFileRevision(),
			null,
			$this->createMock( HttpRequestExecutor::class ),
			$this->createMock( WikiRevisionFactory::class ),
			$this->createMock( UploadBaseFactory::class ),
			$this->newUploadRevisionImporter()
		);

		$this->assertFalse( $fileRevisionFromRemoteUrl->prepare() );
	}

	/**
	 * @return FileRevision
	 */
	private function newMockFileRevision() {
		$mock = $this->createMock( FileRevision::class );
		$mock->method( 'getField' )
			->will( $this->returnValue( 'NOURL' ) );
		return $mock;
	}

	/**
	 * @return ImportableUploadRevisionImporter
	 */
	private function newUploadRevisionImporter() {
		return new ImportableUploadRevisionImporter(
			false,
			new NullLogger()
		);
	}

}
