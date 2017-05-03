<?php

namespace FileImporter\MediaWiki\Test;

use FileImporter\MediaWiki\FileImporterUploadBase;
use MediaWikiTestCase;
use TitleValue;

class FileImporterUploadBaseTest extends MediaWikiTestCase {

	public function setUp() {
		parent::setUp();
		// For testing mark the jpg extension is disallowed
		$this->setMwGlobals( 'wgFileBlacklist', [ 'jpg' ] );
	}

	public function providePerformChecks() {
		return [
			// Title checks
			'fileNameTooLongValidJPEG' =>
				[ str_repeat( 'a', 237 ) .  '.jpg', $this->getGetImagePath( 'imagejpeg' ), false ],
			'disallowedFileExtensionValidJPEG' =>
				[ 'Foo.jpg', $this->getGetImagePath( 'imagejpeg' ), false ],
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
	 * @dataProvider providePerformChecks
	 */
	public function testPerformChecks( $targetTitle, $tempPath, $expected ) {
		$base = new FileImporterUploadBase( new TitleValue( NS_FILE, $targetTitle ), $tempPath );
		$this->assertEquals( $expected, $base->performChecks() );
		unlink( $tempPath ); // delete the file that we created post test
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
