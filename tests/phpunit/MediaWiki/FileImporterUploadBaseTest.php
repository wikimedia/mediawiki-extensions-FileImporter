<?php

namespace FileImporter\Tests\MediaWiki;

use FileImporter\Services\UploadBase\ValidatingUploadBase;
use MediaWiki\MainConfigNames;
use MediaWiki\Title\TitleValue;
use UploadBase;

/**
 * @covers \FileImporter\Services\UploadBase\ValidatingUploadBase
 *
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class FileImporterUploadBaseTest extends \MediaWikiIntegrationTestCase {

	public static function providePerformTitleChecks() {
		return [
			'fileNameTooLongValidJPEG' =>
				[ str_repeat( 'a', 237 ) . '.jpg', UploadBase::FILENAME_TOO_LONG ],
			'disallowedFileExtensionValidJPEG' =>
				[ 'Foo.jpg', UploadBase::FILETYPE_BADTYPE ],
		];
	}

	/**
	 * @dataProvider providePerformTitleChecks
	 */
	public function testPerformTitleChecks( string $targetTitle, int $expected ) {
		$this->overrideConfigValue( MainConfigNames::ProhibitedFileExtensions, [ 'jpg' ] );
		$base = new ValidatingUploadBase(
			new TitleValue( NS_FILE, $targetTitle ),
			''
		);
		$this->assertSame( $expected, $base->validateTitle() );
	}

	public static function providePerformFileChecks() {
		return [
			// File vs title checks
			'validPNG' => [ 'Foo.png', 'png' ],
			'validGIF' => [ 'Foo.gif', 'gif' ],
			'validJPEG' => [ 'Foo.jpeg', 'jpeg' ],
			'PNGwithBadExtension' => [ 'Foo.jpeg', 'png', 'filetype-mime-mismatch' ],
			'GIFwithBadExtension' => [ 'Foo.jpeg', 'gif', 'filetype-mime-mismatch' ],
			'JPEGwithBadExtension' => [ 'Foo.gif', 'jpeg', 'filetype-mime-mismatch' ],
		];
	}

	/**
	 * @dataProvider providePerformFileChecks
	 */
	public function testPerformFileChecks(
		string $targetTitle,
		string $actualFileType,
		?string $expectedError = null
	) {
		$tempPath = $this->getGetImagePath( $actualFileType );
		$base = new ValidatingUploadBase(
			new TitleValue( NS_FILE, $targetTitle ),
			$tempPath
		);
		$status = $base->validateFile();
		if ( $expectedError ) {
			$this->assertStatusError( $expectedError, $status );
		} else {
			$this->assertStatusGood( $status );
		}
	}

	private function getGetImagePath( string $fileType ): string {
		$saveMethod = "image$fileType";
		if ( !function_exists( $saveMethod ) ) {
			$this->markTestSkipped( "$saveMethod function required for this test" );
		}

		$tmpPath = $this->getNewTempFile();
		$im = imagecreate( 16, 16 );
		imagecolorallocate( $im, 255, 0, 255 );
		$saveMethod( $im, $tmpPath );
		imagedestroy( $im );
		return $tmpPath;
	}

}
