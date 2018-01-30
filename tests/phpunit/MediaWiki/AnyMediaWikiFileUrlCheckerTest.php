<?php

namespace FileImporter\MediaWiki\Test;

use FileImporter\Data\SourceUrl;
use FileImporter\Remote\MediaWiki\AnyMediaWikiFileUrlChecker;
use PHPUnit_Framework_TestCase;

/**
 * @covers \FileImporter\Remote\MediaWiki\AnyMediaWikiFileUrlChecker
 */
class AnyMediaWikiFileUrlCheckerTest extends PHPUnit_Framework_TestCase {

	public function provideTestCheck() {
		return [
			[ 'https://en.wikipedia.org/wiki/File:Foo.png', true ],
			[ 'https://en.wikipedia.org/wiki/File:Foo.png#Bar', true ],
			[ 'https://en.wikipedia.org/wiki/File:Foo.png?foo=bar', true ],
			[ 'https://commons.wikimedia.org/w/index.php?title=File:E-2C_Hawkeye_and_Mount_Fuji.jpg', true ],
			// These could be files? We don't know until we make a http request
			[ 'https://commons.wikimedia.org/wiki', true ],
			[ 'https://commons.wikimedia.org/wiki/Foo', true ],
			// A root domain most probably is not going to be an individual mediawiki page
			[ 'https://commons.wikimedia.org', false ],
		];
	}

	/**
	 * @dataProvider provideTestCheck
	 */
	public function testCheck( $url, $expected ) {
		$sourceUrl = new SourceUrl( $url );
		$checker = new AnyMediaWikiFileUrlChecker();
		$result = $checker->checkSourceUrl( $sourceUrl );
		$this->assertSame( $expected, $result );
	}

}
