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

	private function newFileImporterHooks() {
		return new FileImporterHooks(
			$this->getServiceContainer()->getMainConfig()
		);
	}

	public function testOnListDefinedTags() {
		$tags = [];
		$this->newFileImporterHooks()->onListDefinedTags( $tags );
		$this->assertSame(
			[ 'fileimporter', 'fileimporter-imported' ],
			$tags );
	}

	public function testOnUserGetReservedNames() {
		$this->overrideConfigValue( 'FileImporterAccountForSuppressedUsername', '<SUPPRESSED>' );
		$reservedUsernames = [];
		$this->newFileImporterHooks()->onUserGetReservedNames( $reservedUsernames );
		$this->assertSame( [ '<SUPPRESSED>' ], $reservedUsernames );
	}

}
