<?php

namespace FileImporter\Tests\MediaWiki;

use FileImporter\Data\SourceUrl;
use FileImporter\Remote\MediaWiki\AllowedDomainsFileUrlChecker;

/**
 * @covers \FileImporter\Remote\MediaWiki\AllowedDomainsFileUrlChecker
 *
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class AllowedDomainsFileUrlCheckerTest extends \MediaWikiIntegrationTestCase {

	public static function provideTestCheck() {
		return [
			// Success
			[ [ 'en.wikipedia.org' ], '//en.wikipedia.org/wiki/File:Foo.png', true ],
			[ [ '.wikipedia.org' ], '//en.wikipedia.org/wiki/File:Foo.png', true ],
			[ [ '.wikipedia.org', 'la.com' ], '//en.wikipedia.org/wiki/File:Foo.png', true ],
			// Failures
			[ [ 'en.wikipedia.COM' ], '//en.wikipedia.org/wiki/File:Foo.png', false ],
			[ [ 'wikipedia.org' ], '//en.wikipedia.org/wiki/File:Foo.png', false ],
			[ [], '//en.wikipedia.org/wiki/File:Foo.png', false ],
			[ [ 'google.com' ], '//en.wikipedia.org/wiki/File:Foo.png', false ],
			[ [ 'google.com' ], '//en.wikipedia.org/wiki/File:Foo.png', false ],
			[ [ 'google.com', 'foo.bar' ], '//en.wikipedia.org/wiki/File:Foo.png', false ],
		];
	}

	/**
	 * @dataProvider provideTestCheck
	 */
	public function testCheck( array $allowedDomains, string $url, bool $expected ) {
		$sourceUrl = new SourceUrl( $url );
		$checker = new AllowedDomainsFileUrlChecker( $allowedDomains );
		$result = $checker->checkSourceUrl( $sourceUrl );
		$this->assertSame( $expected, $result );
	}

}
