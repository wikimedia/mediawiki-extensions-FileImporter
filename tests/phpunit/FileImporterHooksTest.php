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
		( new FileImporterHooks )->onListDefinedTags( $tags );
		$this->assertSame(
			[ 'fileimporter', 'fileimporter-imported' ],
			$tags );
	}

	public function testOnUserGetReservedNames() {
		$this->overrideConfigValue( 'FileImporterAccountForSuppressedUsername', '<SUPPRESSED>' );
		$reservedUsernames = [];
		( new FileImporterHooks )->onUserGetReservedNames( $reservedUsernames );
		$this->assertSame( [ '<SUPPRESSED>' ], $reservedUsernames );
	}

}
