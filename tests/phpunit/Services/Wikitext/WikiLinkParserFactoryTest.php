<?php
declare( strict_types = 1 );

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
class WikiLinkParserFactoryTest extends \MediaWikiIntegrationTestCase {

	private function newInstance(): WikiLinkParserFactory {
		$services = $this->getServiceContainer();
		return new WikiLinkParserFactory(
			$services->getTitleParser(),
			$services->getNamespaceInfo(),
			$services->getLanguageFactory()
		);
	}

	public function testInterwikiPrefixing() {
		$parser = $this->newInstance()->getWikiLinkParser( null, 'PREFIX' );
		$this->assertSame( '[[:PREFIX:Benutzer:Me]]', $parser->parse( '[[Benutzer:Me]]' ) );
	}

	public function testNamespaceUnlocalization() {
		$parser = $this->newInstance()->getWikiLinkParser( 'de', '' );
		$this->assertSame( '[[User:Me]]', $parser->parse( '[[Benutzer:Me]]' ) );
	}

	public function testWikiLinkNormalization() {
		$parser = $this->newInstance()->getWikiLinkParser( 'de', 'PREFIX' );
		$this->assertSame( '[[:PREFIX:User:Me]]', $parser->parse( '[[Benutzer:Me]]' ) );
	}

}
