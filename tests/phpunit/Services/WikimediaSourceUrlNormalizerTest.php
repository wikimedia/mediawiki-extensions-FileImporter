<?php

namespace FileImporter\Tests\Services;

use FileImporter\Data\SourceUrl;
use FileImporter\Services\WikimediaSourceUrlNormalizer;

/**
 * @covers \FileImporter\Services\MediaWikiSourceUrlNormalizer
 * @covers \FileImporter\Services\WikimediaSourceUrlNormalizer
 *
 * @license GPL-2.0-or-later
 * @author Thiemo Kreuz
 */
class WikimediaSourceUrlNormalizerTest extends \PHPUnit\Framework\TestCase {

	public function provideUrls() {
		return [
			// Intended normalizations
			[ 'https://de.m.wikipedia.org/wiki/File:X.svg', 'https://de.wikipedia.org/wiki/File:X.svg' ],
			[ '//de.m.wikipedia.org', '//de.wikipedia.org' ],
			[ '//de.wikipedia.org/wiki/File:X 2.svg', '//de.wikipedia.org/wiki/File:X_2.svg' ],
			[ " //wiki/X.svg\n", '//wiki/X.svg' ],

			// Edge-cases where replacements are still fine
			[ '//de.m.', '//de.' ],
			[ '//.m.wiki', '//.wiki' ],
			[ '//.m.', '//.' ],

			// No replacements should be made
			[ '//m.de.wikipedia.org', '//m.de.wikipedia.org' ],
			[ '//example.com/File:I.b.m.svg', '//example.com/File:I.b.m.svg' ],
			[ '//example.com/?title=I.b.m.svg', '//example.com/?title=I.b.m.svg' ],
		];
	}

	/**
	 * @dataProvider provideUrls
	 */
	public function testNormalization( $url, $expected ) {
		$normalizer = new WikimediaSourceUrlNormalizer();
		$normalized = $normalizer->normalize( new SourceUrl( $url ) );
		$this->assertSame( $expected, $normalized->getUrl() );
	}

}
