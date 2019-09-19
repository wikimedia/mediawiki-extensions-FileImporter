<?php

namespace FileImporter\Tests\Services\Wikitext;

use FileImporter\Services\Wikitext\WikiLinkParserFactory;

/**
 * Note this test (intentionally) uses actual Language objects, which does make this an integration
 * test with MediaWiki's i18n feature set.
 *
 * @covers \FileImporter\Services\Wikitext\WikiLinkParserFactory
 *
 * @license GPL-2.0-or-later
 * @author Thiemo Kreuz
 */
class WikiLinkParserFactoryTest extends \PHPUnit\Framework\TestCase {

	public function testInterwikiPrefixing() {
		$factory = new WikiLinkParserFactory();
		$parser = $factory->getWikiLinkParser( null, 'PREFIX' );
		$this->assertSame( '[[:PREFIX:Benutzer:Me]]', $parser->parse( '[[Benutzer:Me]]' ) );
	}

	public function testNamespaceUnlocalization() {
		$factory = new WikiLinkParserFactory();
		$parser = $factory->getWikiLinkParser( 'de', '' );
		$this->assertSame( '[[User:Me]]', $parser->parse( '[[Benutzer:Me]]' ) );
	}

	public function testWikiLinkNormalization() {
		$factory = new WikiLinkParserFactory();
		$parser = $factory->getWikiLinkParser( 'de', 'PREFIX' );
		$this->assertSame( '[[:PREFIX:User:Me]]', $parser->parse( '[[Benutzer:Me]]' ) );
	}

}
