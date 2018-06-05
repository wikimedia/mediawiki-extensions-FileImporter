<?php

namespace FileImporter\Tests\Exceptions;

use FileImporter\Exceptions\LocalizedImportException;
use Language;
use MediaWikiTestCase;
use Message;

/**
 * @covers \FileImporter\Exceptions\LocalizedImportException
 *
 * @license GPL-2.0-or-later
 * @author Thiemo Kreuz
 */
class LocalizedImportExceptionTest extends MediaWikiTestCase {

	protected function setUp() {
		parent::setUp();

		$this->setUserLang( 'qqx' );
	}

	public function testGetMessageObject() {
		$ex = new LocalizedImportException( [ 'fileimporter-filetoolarge', 1 ] );

		$expectedMessage = new Message( 'fileimporter-filetoolarge', [ 1 ], Language::factory( 'en' ) );
		$this->assertSame( $expectedMessage->text(), $ex->getMessage() );
		$this->assertSame( 'qqx', $ex->getMessageObject()->getLanguage()->getCode() );
	}

}