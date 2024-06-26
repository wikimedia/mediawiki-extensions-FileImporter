<?php

namespace FileImporter\Tests\Services\Wikitext;

use FileImporter\Services\Wikitext\WikiLinkPrefixer;
use MediaWiki\Title\MalformedTitleException;
use MediaWiki\Title\TitleParser;
use MediaWiki\Title\TitleValue;

/**
 * @covers \FileImporter\Services\Wikitext\WikiLinkPrefixer
 *
 * @license GPL-2.0-or-later
 */
class WikiLinkPrefixerTest extends \MediaWikiIntegrationTestCase {

	public static function provideLinks() {
		return [
			// No-op when no prefix given
			[ '', '', '' ],
			[ 'foo', '', 'foo' ],
			[ 'mw:foo', '', 'mw:foo' ],

			// Links that should be prefixed
			[ 'foo', 'mw', ':mw:foo' ],
			[ ':foo', 'MW', ':MW:foo' ],
			[ ':en:foo', 'mw', ':mw:en:foo' ],
			[ 'Talk:foo', 'mw', ':mw:Talk:foo' ],

			// Already prefixed with the same prefix
			[ ':mw:foo', 'mw', ':mw:foo' ],
			[ 'mw:foo', 'mw', 'mw:foo' ],
			[ 'MW:foo', 'mw', 'MW:foo' ],
			[ 'w:de:foo', 'w:de', 'w:de:foo' ],
			[ ' : w:de : foo', 'w:de', ' : w:de : foo' ],
			[ '::w:de:foo', 'w:de', '::w:de:foo' ],
			[ '兵庫県立考古博物館:Japan', '兵庫県立考古博物館', '兵庫県立考古博物館:Japan', ],

			// Excluded namespaces
			[ ':File:foo', 'mw', ':File:foo' ],
			[ 'File:foo', 'mw', 'File:foo' ],
			[ 'Media:foo', 'mw', 'Media:foo' ],
			[ 'Category:foo', 'mw', 'Category:foo' ],

			// No need to validate the prefix
			[ 'foo', 'whatever', ':whatever:foo' ],

			// Should not do to much normalization
			[ 'no_normalization', 'mw', ':mw:no_normalization' ],
			[ ' foo ', 'mw', ':mw:foo ' ],
			[ ': foo ', 'mw', ':mw: foo ' ],
			[ ':_foo_', 'mw', ':mw:_foo_' ],

			// We can't know (yet) if the existing prefix is a namespace or duplicate interwiki
			[ ':mw:foo', 'w:mw', ':w:mw:mw:foo' ],
			[ 'commons:Commons:foo', 'w', ':w:commons:Commons:foo' ],

			// Invalid titles
			[ '::invalid', 'mw', '::invalid' ],
		];
	}

	/**
	 * @dataProvider provideLinks
	 */
	public function testInterwikiPrefixing( string $link, string $prefix, string $expected ) {
		$prefixer = new WikiLinkPrefixer( $prefix, $this->newTitleParser() );
		$this->assertSame( $expected, $prefixer->process( $link ) );
	}

	private function newTitleParser(): TitleParser {
		$parser = $this->createMock( TitleParser::class );
		$parser->method( 'parseTitle' )->willReturnCallback( static function ( string $text ): TitleValue {
			switch ( ltrim( $text, ':' ) ) {
				case 'w:de:foo':
					return new TitleValue( NS_MAIN, '', '', 'w:de' );
				case 'File:foo':
					return new TitleValue( NS_FILE, $text );
				case 'Media:foo':
					return new TitleValue( NS_MEDIA, $text );
				case 'Category:foo':
					return new TitleValue( NS_CATEGORY, $text );
				case 'invalid':
					throw new MalformedTitleException( '' );
				default:
					return new TitleValue( NS_MAIN, '' );
			}
		} );
		return $parser;
	}

}
