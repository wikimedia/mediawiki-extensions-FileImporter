<?php

namespace FileImporter\Tests\Exceptions;

use FileImporter\Exceptions\LocalizedImportException;

/**
 * @covers \FileImporter\Exceptions\LocalizedImportException
 *
 * @license GPL-2.0-or-later
 * @author Thiemo Kreuz
 */
class LocalizedImportExceptionTest extends \MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();

		$this->setUserLang( 'qqx' );
	}

	public function testGetMessageObject() {
		$ex = new LocalizedImportException( [ 'fileimporter-filetoolarge', 1 ] );

		$this->assertSame( '(fileimporter-filetoolarge: 1)', $ex->getMessage() );
		$this->assertSame( 'qqx', $ex->getMessageObject()->getLanguage()->getCode() );
	}

}
