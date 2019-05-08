<?php

namespace FileImporter\Tests\Services;

use FileImporter\Services\WikiLinkPrefixer;
use MediaWiki\Interwiki\InterwikiLookupAdapter;

/**
 * @covers \FileImporter\Services\WikiLinkPrefixer
 *
 * @license GPL-2.0-or-later
 */
class WikiLinkPrefixerTest extends \PHPUnit\Framework\TestCase {
	use \PHPUnit4And6Compat;

	public function provideLinks() {
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

			// Excluded namespaces
			[ ':File:foo', 'mw', ':File:foo' ],
			[ 'File:foo', 'mw', 'File:foo' ],
			[ 'Category:foo', 'mw', 'Category:foo' ],

			// No need to validate the prefix
			[ 'foo', 'whatever', ':whatever:foo' ],

			// Should not do to much normalization
			[ 'no_normalization', 'mw', ':mw:no_normalization' ],
			[ ' foo ', 'mw', ':mw:foo ' ],
			[ ': foo ', 'mw', ':mw: foo ' ],
			[ ':_foo_', 'mw', ':mw:_foo_' ],

			// Invalid titles
			[ 'Talk:#fragment', 'mw', 'Talk:#fragment' ],
		];
	}

	/**
	 * @dataProvider provideLinks
	 */
	public function testInterwikiPrefixing( $link, $prefix, $expected ) {
		$prefixer = new WikiLinkPrefixer( $prefix, $this->newTitleParser() );
		$this->assertSame( $expected, $prefixer->process( $link ) );
	}

	/**
	 * @return \TitleParser
	 */
	private function newTitleParser() {
		$language = $this->createMock( \Language::class );
		$language->method( 'getNsIndex' )
			->willReturnCallback( function ( $name ) {
				switch ( $name ) {
					case '':
						return NS_MAIN;
					case 'Talk':
						return NS_TALK;
					case 'File':
						return NS_FILE;
					case 'Category':
						return NS_CATEGORY;
					default:
						return false;
				}
			} );
		$language->method( 'lc' )
			->willReturnArgument( 0 );

		$site = new \Site();
		$site->addInterwikiId( 'mw' );
		// The original InterwikiLookup is case-insensitive, this line simulates this feature
		$site->addInterwikiId( 'MW' );

		return new \MediaWikiTitleCodec(
			$language,
			new \GenderCache(),
			[],
			new InterwikiLookupAdapter( new \HashSiteStore( [ $site ] ) ),
			// Note: As of now, MediaWikiTitleCodec does not use this NamespaceInfo, but asks the
			// Language for info about namespaces!
			$this->createMock( \NamespaceInfo::class )
		);
	}

}
