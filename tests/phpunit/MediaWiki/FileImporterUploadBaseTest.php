<?php

namespace FileImporter\Tests\MediaWiki;

use FileImporter\Services\UploadBase\ValidatingUploadBase;
use PHPUnit\Framework\Assert;
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
		$base = new ValidatingUploadBase(
			new TitleValue( NS_FILE, $targetTitle ),
			''
		);
		$this->assertSame( $expected, $base->validateTitle() );
	}

	public static function providePerformFileChecks() {
		self::skipTestIfImageFunctionsMissing();

		return [
			// File vs title checks
			'validPNG' => [ 'Foo.png', self::getGetImagePath( 'imagepng' ) ],
			'validGIF' => [ 'Foo.gif', self::getGetImagePath( 'imagegif' ) ],
			'validJPEG' => [ 'Foo.jpeg', self::getGetImagePath( 'imagejpeg' ) ],
			'PNGwithBadExtension' => [ 'Foo.jpeg', self::getGetImagePath( 'imagepng' ),
				'filetype-mime-mismatch' ],
			'GIFwithBadExtension' => [ 'Foo.jpeg', self::getGetImagePath( 'imagegif' ),
				'filetype-mime-mismatch' ],
			'JPEGwithBadExtension' => [ 'Foo.gif', self::getGetImagePath( 'imagejpeg' ),
				'filetype-mime-mismatch' ],
		];
	}

	/**
	 * @dataProvider providePerformFileChecks
	 */
	public function testPerformFileChecks(
		string $targetTitle,
		string $tempPath,
		string $expectedError = null
	) {
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
		// Delete the file that we created post test
		unlink( $tempPath );
	}

	private static function skipTestIfImageFunctionsMissing(): void {
		if (
			!function_exists( 'imagejpeg' ) ||
			!function_exists( 'imagepng' ) ||
			!function_exists( 'imagegif' )
		) {
			Assert::markTestSkipped( 'image* functions required for this test.' );
		}
	}

	/**
	 * @param string $saveMethod one of imagepng, imagegif, imagejpeg
	 *
	 * @return string tmp image file path
	 */
	private static function getGetImagePath( string $saveMethod ): string {
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
