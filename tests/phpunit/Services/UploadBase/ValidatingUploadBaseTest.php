<?php

namespace FileImporter\Tests\Services\UploadBase;

use FileImporter\Services\UploadBase\ValidatingUploadBase;
use MediaWiki\Linker\LinkTarget;
use Psr\Log\NullLogger;
use TitleValue;
use UploadBase;

/**
 * @covers \FileImporter\Services\UploadBase\ValidatingUploadBase
 *
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class ValidatingUploadBaseTest extends \MediaWikiTestCase {

	protected function setUp() {
		parent::setUp();

		$this->setMwGlobals( [
			'wgFileExtensions' => [ 'jpg' ],
		] );
	}

	public function provideValidateTitle() {
		return [
			'valid title' =>
				[ new TitleValue( NS_FILE, 'ValidTitle.JPG' ), true ],
			'bad file extension' =>
				[ new TitleValue( NS_FILE, 'InvalidExtension.png' ), UploadBase::FILETYPE_BADTYPE ],
			'too long title' =>
				[ new TitleValue( NS_FILE, str_repeat( 'a', 300 ) ), UploadBase::FILENAME_TOO_LONG ],
		];
	}

	/**
	 * @dataProvider provideValidateTitle
	 */
	public function testValidateTitle( LinkTarget $linkTarget, $expected ) {
		$base = new ValidatingUploadBase(
			new NullLogger(),
			$linkTarget,
			''
		);
		$this->assertSame( $expected, $base->validateTitle() );
	}

}
