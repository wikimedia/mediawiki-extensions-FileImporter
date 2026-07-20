<?php
declare( strict_types = 1 );

namespace FileImporter\Tests\MediaWiki;

use FileImporter\Services\UploadBase\ValidatingUploadBase;
use MediaWiki\MainConfigNames;
use MediaWiki\Title\TitleValue;
use MediaWiki\Upload\UploadBase;

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
		foreach ( [ 'png', 'gif', 'jpeg' ] as $ext ) {
			$saveMethod = "image$ext";
			if ( function_exists( $saveMethod ) ) {
				$filename = "Foo.$ext";
				$mismatchingFilename = $ext === 'jpeg' ? 'Foo.gif' : 'Foo.jpeg';
				yield [ $filename, $saveMethod ];
				yield [ $mismatchingFilename, $saveMethod, 'filetype-mime-mismatch' ];
			}
		}
	}

	/**
	 * @dataProvider providePerformFileChecks
	 */
	public function testPerformFileChecks(
		string $targetTitle,
		callable $saveMethod,
		?string $expectedError = null
	) {
		$tempPath = $this->getGetImagePath( $saveMethod );
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

	private function getGetImagePath( callable $saveMethod ): string {
		$tmpPath = $this->getNewTempFile();
		$im = imagecreate( 16, 16 );
		imagecolorallocate( $im, 255, 0, 255 );
		$saveMethod( $im, $tmpPath );
		return $tmpPath;
	}

}
