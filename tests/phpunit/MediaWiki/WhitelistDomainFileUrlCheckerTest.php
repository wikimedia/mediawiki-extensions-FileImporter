<?php

namespace FileImporter\Tests\MediaWiki;

use FileImporter\Data\SourceUrl;
use FileImporter\Remote\MediaWiki\WhitelistDomainFileUrlChecker;

/**
 * @covers \FileImporter\Remote\MediaWiki\WhitelistDomainFileUrlChecker
 *
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class WhitelistDomainFileUrlCheckerTest extends \PHPUnit\Framework\TestCase {

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
	public function testCheck( $whiteList, $url, $expected ) {
		$sourceUrl = new SourceUrl( $url );
		$checker = new WhitelistDomainFileUrlChecker( $whiteList );
		$result = $checker->checkSourceUrl( $sourceUrl );
		$this->assertSame( $expected, $result );
	}

}
