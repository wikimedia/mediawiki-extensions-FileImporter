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
class AllowedDomainsFileUrlCheckerTest extends \PHPUnit\Framework\TestCase {

	public function provideTestCheck() {
		return [
			// Success
			[ [ 'en.wikipedia.org' ], 'https://en.wikipedia.org/wiki/File:Foo.png', true ],
			[ [ '.wikipedia.org' ], 'https://en.wikipedia.org/wiki/File:Foo.png', true ],
			[ [ '.wikipedia.org', 'la.com' ], 'https://en.wikipedia.org/wiki/File:Foo.png', true ],
			// Failures
			[ [ 'en.wikipedia.COM' ], 'https://en.wikipedia.org/wiki/File:Foo.png', false ],
			[ [ 'wikipedia.org' ], 'https://en.wikipedia.org/wiki/File:Foo.png', false ],
			[ [], 'https://en.wikipedia.org/wiki/File:Foo.png', false ],
			[ [ 'google.com' ], 'https://en.wikipedia.org/wiki/File:Foo.png', false ],
			[ [ 'google.com' ], 'https://en.wikipedia.org/wiki/File:Foo.png', false ],
			[ [ 'google.com', 'foo.bar' ], 'https://en.wikipedia.org/wiki/File:Foo.png', false ],
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
