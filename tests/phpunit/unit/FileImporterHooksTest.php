<?php
declare( strict_types = 1 );

namespace FileImporter\Tests;

use FileImporter\FileImporterHooks;
use MediaWiki\Config\HashConfig;

/**
 * @covers \FileImporter\FileImporterHooks
 *
 * @license GPL-2.0-or-later
 * @author Thiemo Kreuz
 */
class FileImporterHooksTest extends \MediaWikiUnitTestCase {

	private function newInstance(): FileImporterHooks {
		return new FileImporterHooks( new HashConfig( [
			'FileImporterAccountForSuppressedUsername' => '<SUPPRESSED>',
		] ) );
	}

	public function testOnListDefinedTags() {
		$tags = [];
		$this->newInstance()->onListDefinedTags( $tags );
		$this->assertSame(
			[ 'fileimporter', 'fileimporter-imported' ],
			$tags );
	}

	public function testOnUserGetReservedNames() {
		$reservedUsernames = [];
		$this->newInstance()->onUserGetReservedNames( $reservedUsernames );
		$this->assertSame( [ '<SUPPRESSED>' ], $reservedUsernames );
	}

}
