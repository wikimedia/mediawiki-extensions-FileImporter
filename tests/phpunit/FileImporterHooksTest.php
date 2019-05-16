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

	public function testOnListDefinedTags() {
		$tags = [];
		FileImporterHooks::onListDefinedTags( $tags );
		$this->assertSame( [ 'fileimporter' ], $tags );
	}

	public function testOnUserGetReservedNames() {
		$this->setMwGlobals( 'wgFileImporterAccountForSuppressedUsername', '<SUPPRESSED>' );
		$reservedUsernames = [];
		FileImporterHooks::onUserGetReservedNames( $reservedUsernames );
		$this->assertSame( [ '<SUPPRESSED>' ], $reservedUsernames );
	}

}
