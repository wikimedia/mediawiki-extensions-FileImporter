<?php

namespace FileImporter\Services\Test\UploadBase;

use FileImporter\Services\UploadBase\ValidatingUploadBase;
use MediaWiki\Linker\LinkTarget;
use MediaWikiTestCase;
use Psr\Log\NullLogger;
use TitleValue;
use UploadBase;

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
