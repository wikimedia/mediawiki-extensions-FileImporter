<?php

namespace FileImporter\Tests\Services;

use FileImporter\Services\WikiLinkParser;

/**
 * @covers \FileImporter\Services\WikiLinkParser
 *
 * @license GPL-2.0-or-later
 * @author Thiemo Kreuz
 */
class WikiLinkParserTest extends \PHPUnit\Framework\TestCase {
	use \PHPUnit4And6Compat;

	public function provideWikiText() {
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
	 * @dataProvider provideWikiText
	 */
	public function testParser( $wikiText, $expected ) {
		$parser = new WikiLinkParser();
		$this->assertSame( $wikiText, $parser->parse( $wikiText ), 'no cleaner registered' );

		// Dummy replacements for testing purposes only, as well as to test the execution order
		$parser->registerWikiLinkCleaner( function ( $link ) {
			$this->assertNotSame( '', $link, 'does not process empty strings' );
			return strtolower( $link );
		} );
		$parser->registerWikiLinkCleaner( function ( $link ) {
			return 'Prefix>' . $link;
		} );
		$this->assertSame( $expected, $parser->parse( $wikiText ) );
	}

}
