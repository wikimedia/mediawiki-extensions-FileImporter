<?php

namespace FileImporter\Tests\MediaWiki;

use FileImporter\Services\UploadBase\ValidatingUploadBase;
use TitleValue;
use UploadBase;

/**
 * @covers \FileImporter\Services\UploadBase\ValidatingUploadBase
 *
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class FileImporterUploadBaseTest extends \MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		// For testing mark the jpg extension is disallowed
		$this->setMwGlobals( 'wgProhibitedFileExtensions', [ 'jpg' ] );
	}

	public function providePerformTitleChecks() {
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
	public function testPerformTitleChecks( $targetTitle, $expected ) {
		$base = new ValidatingUploadBase(
			new TitleValue( NS_FILE, $targetTitle ),
			''
		);
		$this->assertSame( $expected, $base->validateTitle() );
	}

	public function providePerformFileChecks() {
		$this->skipTestIfImageFunctionsMissing();

		return [
			// File vs title checks
			'validPNG' => [ 'Foo.png', $this->getGetImagePath( 'imagepng' ), true, null ],
			'validGIF' => [ 'Foo.gif', $this->getGetImagePath( 'imagegif' ), true, null ],
			'validJPEG' => [ 'Foo.jpeg', $this->getGetImagePath( 'imagejpeg' ), true, null ],
			'PNGwithBadExtension' => [ 'Foo.jpeg', $this->getGetImagePath( 'imagepng' ),
				false, 'filetype-mime-mismatch' ],
			'GIFwithBadExtension' => [ 'Foo.jpeg', $this->getGetImagePath( 'imagegif' ),
				false, 'filetype-mime-mismatch' ],
			'JPEGwithBadExtension' => [ 'Foo.gif', $this->getGetImagePath( 'imagejpeg' ),
				false, 'filetype-mime-mismatch' ],
		];
	}

	/**
	 * @dataProvider providePerformFileChecks
	 */
	public function testPerformFileChecks( $targetTitle, $tempPath,
		$expectedSuccess, $expectedError
	) {
		$base = new ValidatingUploadBase(
			new TitleValue( NS_FILE, $targetTitle ),
			$tempPath
		);
		$status = $base->validateFile();
		$this->assertSame( $expectedSuccess, $status->isOK() );
		if ( $expectedError ) {
			$this->assertTrue( $status->hasMessage( $expectedError ) );
		}
		// Delete the file that we created post test
		unlink( $tempPath );
	}

	private function skipTestIfImageFunctionsMissing() {
		if (
			!function_exists( 'imagejpeg' ) ||
			!function_exists( 'imagepng' ) ||
			!function_exists( 'imagegif' )
		) {
			$this->markTestSkipped( 'image* functions required for this test.' );
		}
	}

	/**
	 * @param string $saveMethod one of imagepng, imagegif, imagejpeg
	 *
	 * @return string tmp image file path
	 */
	private function getGetImagePath( $saveMethod ) {
		$tmpPath = tempnam( sys_get_temp_dir(), __CLASS__ );
		$im = imagecreate( 100, 100 );
		imagecolorallocate( $im, 0, 0, 0 );
		$text_color = imagecolorallocate( $im, 233, 14, 91 );
		imagestring( $im, 1, 5, 5, 'Some Text', $text_color );
		$saveMethod( $im, $tmpPath );
		imagedestroy( $im );
		return $tmpPath;
	}

}
