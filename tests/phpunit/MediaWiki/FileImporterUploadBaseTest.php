<?php

namespace FileImporter\MediaWiki\Test;

use FileImporter\Services\UploadBase\ValidatingUploadBase;
use MediaWikiTestCase;
use Psr\Log\NullLogger;
use TitleValue;
use UploadBase;

/**
 * @covers \FileImporter\Services\UploadBase\ValidatingUploadBase
 */
class FileImporterUploadBaseTest extends MediaWikiTestCase {

	public function setUp() {
		parent::setUp();
		// For testing mark the jpg extension is disallowed
		$this->setMwGlobals( 'wgFileBlacklist', [ 'jpg' ] );
	}

	public function providePerformTitleChecks() {
		return [
			'fileNameTooLongValidJPEG' =>
				[ str_repeat( 'a', 237 ) .  '.jpg', UploadBase::FILENAME_TOO_LONG ],
			'disallowedFileExtensionValidJPEG' =>
				[ 'Foo.jpg', UploadBase::FILETYPE_BADTYPE ],
		];
	}

	/**
	 * @dataProvider providePerformTitleChecks
	 */
	public function testPerformTitleChecks( $targetTitle, $expected ) {
		$base = new ValidatingUploadBase(
			new NullLogger(),
			new TitleValue( NS_FILE, $targetTitle ),
			''
		);
		$this->assertEquals( $expected, $base->validateTitle() );
	}

	public function providePerformFileChecks() {
		$this->skipTestIfImageFunctionsMissing();

		return [
			// File vs title checks
			'validPNG' => [ 'Foo.png', $this->getGetImagePath( 'imagepng' ), true ],
			'validGIF' => [ 'Foo.gif', $this->getGetImagePath( 'imagegif' ), true ],
			'validJPEG' => [ 'Foo.jpeg', $this->getGetImagePath( 'imagejpeg' ), true ],
			'PNGwithBadExtension' => [ 'Foo.jpeg', $this->getGetImagePath( 'imagepng' ), false ],
			'GIFwithBadExtension' => [ 'Foo.jpeg', $this->getGetImagePath( 'imagegif' ), false ],
			'JPEGwithBadExtension' => [ 'Foo.gif', $this->getGetImagePath( 'imagejpeg' ), false ],
		];
	}

	/**
	 * @dataProvider providePerformFileChecks
	 */
	public function testPerformFileChecks( $targetTitle, $tempPath, $expected ) {
		$base = new ValidatingUploadBase(
			new NullLogger(),
			new TitleValue( NS_FILE, $targetTitle ),
				$tempPath
		);
		$this->assertEquals( $expected, $base->validateFile() );
		unlink( $tempPath ); // delete the file that we created post test
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
