<?php

namespace FileImporter\Tests\Services\Wikitext;

use FileImporter\Services\Wikitext\NamespaceUnlocalizer;

/**
 * @covers \FileImporter\Services\Wikitext\NamespaceUnlocalizer
 *
 * @license GPL-2.0-or-later
 */
class NamespaceUnlocalizerTest extends \PHPUnit\Framework\TestCase {
	use \PHPUnit4And6Compat;

	public function provideLinks() {
		return [
			// Nothing to do
			[ '', '' ],
			[ 'foo', 'foo' ],
			[ 'Category:foo', 'Category:foo' ],

			// Successfull replacements
			[ 'Kategorie:foo', 'Category:foo' ],
			[ ':Kategorie:foo', ':Category:foo' ],
			[ ' Kategorie:foo', ' Category:foo' ],
			[ 'Kategorie :foo', 'Category :foo' ],
			[ 'Kategorie: foo', 'Category: foo' ],
			[ ' :Kategorie:foo', ' :Category:foo' ],
			[ ': Kategorie:foo', ': Category:foo' ],
			[ "Kategorie:foo\n", "Category:foo\n" ],
			[ 'Kategorie:#fragment', 'Category:#fragment' ],

			// Normalization of multi-word namespace names
			[ 'Kategorie Diskussion:foo', 'Category talk:foo' ],
			[ 'kategorie diskussion:foo', 'Category talk:foo' ],
			[ 'Kategorie_Diskussion:foo', 'Category talk:foo' ],
			[ 'Kategorie__Diskussion:foo', 'Category talk:foo' ],
			[ '_Kategorie_Diskussion_:_foo_', 'Category talk:_foo_' ],
			[ "Kategorie \u{00A0} Diskussion:foo", 'Category talk:foo' ],
			[ "\u{2000}Kategorie Diskussion\u{3000}:foo", 'Category talk:foo' ],

			// Interwiki links might break when fiddled with, so don't do it
			[ 'de:Kategorie:foo', 'de:Kategorie:foo' ],
			[ ':de:Kategorie:foo', ':de:Kategorie:foo' ],

			// Invalid links
			[ '::Kategorie:foo', '::Kategorie:foo' ],
			[ 'Kategorie::foo', 'Kategorie::foo' ],
			[ "\nKategorie:foo", "\nKategorie:foo" ],
			[ "Kategorie\n:foo", "Kategorie\n:foo" ],
			[ "Kategorie:\nfoo", "Kategorie:\nfoo" ],
			[ 'Kategorie:', 'Kategorie:' ],
		];
	}

	/**
	 * @dataProvider provideLinks
	 */
	public function testInterwikiPrefixing( $link, $expected ) {
		$language = $this->createMock( \Language::class );
		$language->method( 'getLocalNsIndex' )
			->willReturnCallback( function ( $name ) {
				switch ( strtolower( $name ) ) {
					case 'kategorie':
						return NS_CATEGORY;
					case 'kategorie_diskussion':
						return NS_CATEGORY_TALK;
					default:
						return false;
				}
			} );

		$namespaceInfo = $this->createMock( \NamespaceInfo::class );
		$namespaceInfo->method( 'getCanonicalName' )
			->willReturnCallback( function ( $index ) {
				switch ( $index ) {
					case NS_CATEGORY:
						return 'Category';
					case NS_CATEGORY_TALK:
						return 'Category_talk';
					default:
						return false;
				}
			} );

		$cleaner = new NamespaceUnlocalizer( $language, $namespaceInfo );
		$this->assertSame( $expected, $cleaner->process( $link ) );
	}

}
