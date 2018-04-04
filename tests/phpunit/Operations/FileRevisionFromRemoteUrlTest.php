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
use Title;
use User;

/**
 * @covers \FileImporter\Operations\FileRevisionFromRemoteUrl
 *
 * @license GPL-2.0-or-later
 * @author Christoph Jauera <christoph.jauera@wikimedia.de>
 */
class FileRevisionFromRemoteUrlTest extends MediaWikiTestCase {

	public function provideTestNewFileRevisionFromRemoteUrl() {
		return [
			[
				null
			],
			[
				$this->newMockTextRevision()
			],
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
			$this->newMockHttpRequestExecutor(),
			$this->newMockWikiRevisionFactory(),
			$this->newMockUploadBaseFactory(),
			$this->newUploadRevisionImporter(),
			new NullLogger()
		);

		$this->addToAssertionCount( 1 );
	}

	public function testFileRevisionFromRemoteUrlPrepareWithBrokenUrl() {
		$fileRevisionFromRemoteUrl = new FileRevisionFromRemoteUrl(
			Title::newFromText( 'Test' ),
			User::newFromName( 'TestUser' ),
			$this->newMockFileRevision(),
			null,
			$this->newMockHttpRequestExecutor(),
			$this->newMockWikiRevisionFactory(),
			$this->newMockUploadBaseFactory(),
			$this->newUploadRevisionImporter(),
			new NullLogger()
		);

		$this->assertFalse( $fileRevisionFromRemoteUrl->prepare() );
	}

	/**
	 * @return FileRevision
	 */
	private function newMockFileRevision() {
		$mock = $this->getMockBuilder( FileRevision::class )
			->disableOriginalConstructor()
			->getMock();
		$mock->method( 'getField' )
			->will( $this->returnValue( 'NOURL' ) );
		return $mock;
	}

	/**
	 * @return TextRevision
	 */
	private function newMockTextRevision() {
		return $this->getMockBuilder( TextRevision::class )
			->disableOriginalConstructor()
			->getMock();
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

	/**
	 * @return UploadBaseFactory
	 */
	private function newMockUploadBaseFactory() {
		return $this->getMockBuilder( UploadBaseFactory::class )
			->disableOriginalConstructor()
			->getMock();
	}

	/**
	 * @return WikiRevisionFactory
	 */
	private function newMockWikiRevisionFactory() {
		return $this->getMockBuilder( WikiRevisionFactory::class )
			->disableOriginalConstructor()
			->getMock();
	}

	/**
	 * @return HttpRequestExecutor
	 */
	private function newMockHttpRequestExecutor() {
		return $this->getMockBuilder( HttpRequestExecutor::class )
			->disableOriginalConstructor()
			->getMock();
	}
}
