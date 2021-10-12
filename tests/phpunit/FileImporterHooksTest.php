<?php

namespace FileImporter\Tests;

use FileImporter\FileImporterHooks;

/**
 * @covers \FileImporter\FileImporterHooks
 *
 * @license GPL-2.0-or-later
 * @author Thiemo Kreuz
 */
class FileImporterHooksTest extends \MediaWikiIntegrationTestCase {

	public function testOnListDefinedTags() {
		$tags = [];
		FileImporterHooks::onListDefinedTags( $tags );
		$this->assertSame(
			[ 'fileimporter', 'fileimporter-imported' ],
			$tags );
	}

	public function testOnUserGetReservedNames() {
		$this->setMwGlobals( 'wgFileImporterAccountForSuppressedUsername', '<SUPPRESSED>' );
		$reservedUsernames = [];
		FileImporterHooks::onUserGetReservedNames( $reservedUsernames );
		$this->assertSame( [ '<SUPPRESSED>' ], $reservedUsernames );
	}

}
