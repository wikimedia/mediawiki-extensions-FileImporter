<?php

namespace FileImporter\Tests\Services\Wikitext;

use FileImporter\Services\Wikitext\NamespaceNameLookup;
use FileImporter\Services\Wikitext\NamespaceUnlocalizer;

/**
 * @covers \FileImporter\Services\Wikitext\NamespaceUnlocalizer
 *
 * @license GPL-2.0-or-later
 */
class NamespaceUnlocalizerTest extends \PHPUnit\Framework\TestCase {

	public function provideLinks() {
		return [
			// Nothing to do
			[ '', '' ],
			[ 'foo', 'foo' ],
			[ 'Category:foo', 'Category:foo' ],
			[ 'Wikipedia:foo', 'Wikipedia:foo' ],

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
			[ '兵庫県立考古博物館:Japan', 'Category:Japan' ],

			// Normalization of multi-word namespace names
			[ 'Kategorie Diskussion:foo', 'Category talk:foo' ],
			[ 'kategorie diskussion:foo', 'Category talk:foo' ],
			[ 'Kategorie_Diskussion:foo', 'Category talk:foo' ],
			[ 'Kategorie__Diskussion:foo', 'Category talk:foo' ],
			[ '_Kategorie_Diskussion_:_foo_', 'Category talk:_foo_' ],
			[ "Kategorie \u{00A0} Diskussion:foo", 'Category talk:foo' ],
			[ "\u{2000}Kategorie Diskussion\u{3000}:foo","\u{2000}Category talk\u{3000}:foo" ],

			// Interwiki links might break when fiddled with, so don't do it
			[ 'de:Kategorie:foo', 'de:Kategorie:foo' ],
			[ ':de:Kategorie:foo', ':de:Kategorie:foo' ],

			// As long as the first prefix clearly is a localized namespace, go for it
			[ 'Kategorie:Kategorie:', 'Category:Kategorie:' ],
			[ 'Kategorie:Kategorie:foo', 'Category:Kategorie:foo' ],

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
		$namespaceNameLookup = $this->createMock( NamespaceNameLookup::class );
		$namespaceNameLookup->method( 'getIndex' )
			->willReturnMap( [
				[ 'Kategorie', NS_CATEGORY ],
				[ 'Kategorie_Diskussion', NS_CATEGORY_TALK ],
				[ 'kategorie_diskussion', NS_CATEGORY_TALK ],
				[ 'Wikipedia', NS_PROJECT ],
				[ '兵庫県立考古博物館', NS_CATEGORY ],
			] );

		$namespaceInfo = $this->createMock( \NamespaceInfo::class );
		$namespaceInfo->method( 'getCanonicalName' )
			->willReturnCallback( static function ( $index ) {
				switch ( $index ) {
					case NS_PROJECT:
						return 'Project';
					case NS_CATEGORY:
						return 'Category';
					case NS_CATEGORY_TALK:
						return 'Category_talk';
					default:
						return false;
				}
			} );

		$cleaner = new NamespaceUnlocalizer( $namespaceNameLookup, $namespaceInfo );
		$this->assertSame( $expected, $cleaner->process( $link ) );
	}

}
