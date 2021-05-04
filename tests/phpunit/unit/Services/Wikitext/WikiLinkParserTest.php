<?php

namespace FileImporter\Tests\Services\Wikitext;

use FileImporter\Services\Wikitext\WikiLinkCleaner;
use FileImporter\Services\Wikitext\WikiLinkParser;

/**
 * @covers \FileImporter\Services\Wikitext\WikiLinkParser
 *
 * @license GPL-2.0-or-later
 * @author Thiemo Kreuz
 */
class WikiLinkParserTest extends \MediaWikiUnitTestCase {

	public function provideWikitext() {
		return [
			'empty brackets' => [
				'[[]]',
				'[[]]',
			],
			'empty link target' => [
				'[[|Text]]',
				'[[|Text]]',
			],
			'without link text' => [
				'[[First]]',
				'[[Prefix>first]]',
			],
			'Unicode' => [
				'[[兵庫県立考古博物館]]展示。',
				'[[Prefix>兵庫県立考古博物館]]展示。',
			],
			'with link text' => [
				'Prefix [[First|Text]], [[Last|Other Text]].',
				'Prefix [[Prefix>first|Text]], [[Prefix>last|Other Text]].',
			],
			'more than 2 opening brackets' => [
				// Note this is intentionally different from the actual parser, which would try to
				// link to the invalid title "[First".
				'[[[First]]',
				'[[[Prefix>first]]',
			],
			'more than 2 closing brackets' => [
				'[[First]]]',
				'[[Prefix>first]]]',
			],
			'nested' => [
				'[[First [[Second]] Third]]',
				'[[First [[Prefix>second]] Third]]',
			],
			'whitespace is not removed' => [
				'[[ First ]]',
				'[[Prefix> first ]]',
			],
			'training colon is not removed' => [
				'[[:de:Beispiel]]',
				'[[Prefix>:de:beispiel]]',
			],
			'incomplete closing brackets' => [
				'[[Incomplete]',
				'[[Incomplete]',
			],
			'vertical whitespace' => [
				"[[Invalid\nLink]]",
				"[[Invalid\nLink]]",
			],
			'end of text' => [
				'[[Broken',
				'[[Broken',
			],
		];
	}

	/**
	 * @dataProvider provideWikitext
	 */
	public function testParser( $wikitext, $expected ) {
		$parser = new WikiLinkParser();
		$this->assertSame( $wikitext, $parser->parse( $wikitext ), 'no cleaner registered' );

		$toLowerCleaner = $this->createMock( WikiLinkCleaner::class );
		$toLowerCleaner->method( 'process' )
			->willReturnCallback( function ( $link ) {
				$this->assertNotSame( '', $link, 'does not process empty strings' );
				return strtolower( $link );
			} );

		$prefixingCleaner = $this->createMock( WikiLinkCleaner::class );
		$prefixingCleaner->method( 'process' )
			->willReturnCallback( static function ( $link ) {
				return 'Prefix>' . $link;
			} );

		// Dummy replacements for testing purposes only, as well as to test the execution order
		$parser->registerWikiLinkCleaner( $toLowerCleaner );
		$parser->registerWikiLinkCleaner( $prefixingCleaner );
		$this->assertSame( $expected, $parser->parse( $wikitext ) );
	}

}
