<?php

namespace FileImporter\Tests;

use FileImporter\FileImporterHooks;

/**
 * @covers \FileImporter\FileImporterHooks
 *
 * @license GPL-2.0-or-later
 * @author Thiemo Kreuz
 */
class FileImporterHooksTest extends \MediaWikiTestCase {

	protected function setUp() {
		parent::setUp();

		$this->setMwGlobals( 'wgFileImporterAccountForSuppressedUsername', '<SUPPRESSED>' );
	}

	public function testOnUserGetReservedNames() {
		$reservedUsernames = [];
		FileImporterHooks::onUserGetReservedNames( $reservedUsernames );
		$this->assertSame( [ '<SUPPRESSED>' ], $reservedUsernames );
	}

}
