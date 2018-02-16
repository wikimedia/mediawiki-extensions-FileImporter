<?php

namespace FileImporter\Services\UploadBase\Test;

use FileImporter\Services\UploadBase\ValidatingUploadBase;
use MediaWiki\Linker\LinkTarget;
use MediaWikiTestCase;
use Psr\Log\NullLogger;
use TitleValue;
use UploadBase;

/**
 * @covers \FileImporter\Services\UploadBase\ValidatingUploadBase
 */
class ValidatingUploadBaseTest extends MediaWikiTestCase {

	public function provideValidateTitle() {
		return [
			[ new TitleValue( NS_FILE, 'ValidTitle.JPG' ), true ],
			[ new TitleValue( NS_FILE, 'OhNoes.JPG/SubPage.JPG' ), UploadBase::FILETYPE_BADTYPE ],
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
		$this->assertEquals( $expected, $base->validateTitle() );
	}

}
