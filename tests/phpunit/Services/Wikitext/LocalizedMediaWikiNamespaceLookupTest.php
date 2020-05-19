<?php

namespace FileImporter\Tests\Services\Wikitext;

use FileImporter\Services\Wikitext\LocalizedMediaWikiNamespaceLookup;
use MWException;

/**
 * @covers \FileImporter\Services\Wikitext\LocalizedMediaWikiNamespaceLookup
 *
 * @license GPL-2.0-or-later
 */
class LocalizedMediaWikiNamespaceLookupTest extends \MediaWikiIntegrationTestCase {

	public function testInvalidLanguageCode() {
		$this->expectException( MWException::class );
		new LocalizedMediaWikiNamespaceLookup( '|' );
	}

	public function testInvalidNamespaceName() {
		$lookup = new LocalizedMediaWikiNamespaceLookup( 'qqx' );
		$this->assertFalse( $lookup->getIndex( 1 ) );
	}

	public function testSuccess() {
		$lookup = new LocalizedMediaWikiNamespaceLookup( 'qqx' );
		$this->assertSame( 1, $lookup->getIndex( 'Talk' ) );
	}

}
