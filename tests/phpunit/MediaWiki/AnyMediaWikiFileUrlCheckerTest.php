<?php
declare( strict_types = 1 );

namespace FileImporter\Tests\MediaWiki;

use FileImporter\Data\SourceUrl;
use FileImporter\Remote\MediaWiki\AnyMediaWikiFileUrlChecker;

/**
 * @covers \FileImporter\Remote\MediaWiki\AnyMediaWikiFileUrlChecker
 *
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class AnyMediaWikiFileUrlCheckerTest extends \MediaWikiIntegrationTestCase {

	public static function provideTestCheck() {
		return [
			[ '//en.wikipedia.org/wiki/File:Foo.png', true ],
			[ '//en.wikipedia.org/wiki/File:Foo.png#Bar', true ],
			[ '//en.wikipedia.org/wiki/File:Foo.png?foo=bar', true ],
			[ '//commons.wikimedia.org/w/index.php?title=File:E-2C_Hawkeye_and_Mount_Fuji.jpg', true ],
			[ '//commons.wikimedia.org/w/index.php?title=', false ],
			// These could be files? We don't know until we make a http request
			[ '//commons.wikimedia.org/wiki', true ],
			[ '//commons.wikimedia.org/wiki/Foo', true ],
			// A root domain most probably is not going to be an individual mediawiki page
			[ '//commons.wikimedia.org', false ],
		];
	}

	/**
	 * @dataProvider provideTestCheck
	 */
	public function testCheck( string $url, bool $expected ) {
		$sourceUrl = new SourceUrl( $url );
		$checker = new AnyMediaWikiFileUrlChecker();
		$result = $checker->checkSourceUrl( $sourceUrl );
		$this->assertSame( $expected, $result );
	}

}
