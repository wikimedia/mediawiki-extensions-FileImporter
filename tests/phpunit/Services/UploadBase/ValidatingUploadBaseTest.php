<?php
declare( strict_types = 1 );

namespace FileImporter\Tests\Services\UploadBase;

use FileImporter\Services\UploadBase\ValidatingUploadBase;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\MainConfigNames;
use MediaWiki\Title\TitleValue;
use UploadBase;

/**
 * @covers \FileImporter\Services\UploadBase\ValidatingUploadBase
 *
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class ValidatingUploadBaseTest extends \MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();

		$this->overrideConfigValue( MainConfigNames::FileExtensions, [ 'jpg' ] );
	}

	public static function provideValidateTitle() {
		return [
			'valid title' =>
				[ new TitleValue( NS_FILE, 'ValidTitle.JPG' ), UploadBase::OK ],
			'bad file extension' =>
				[ new TitleValue( NS_FILE, 'InvalidExtension.png' ), UploadBase::FILETYPE_BADTYPE ],
			'too long title' =>
				[ new TitleValue( NS_FILE, str_repeat( 'a', 300 ) ), UploadBase::FILENAME_TOO_LONG ],
		];
	}

	/**
	 * @dataProvider provideValidateTitle
	 */
	public function testValidateTitle( LinkTarget $linkTarget, int $expected ) {
		$base = new ValidatingUploadBase(
			$linkTarget,
			''
		);
		$this->assertSame( $expected, $base->validateTitle() );
	}

}
