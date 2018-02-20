<?php

namespace FileImporter\MediaWiki\Test;

use FileImporter\Remote\MediaWiki\SiteTableSiteLookup;
use HashSiteStore;
use MediaWikiSite;
use Site;

/**
 * @covers \FileImporter\Remote\MediaWiki\SiteTableSiteLookup
 */
class SiteTableSiteLookupTest extends \PHPUnit\Framework\TestCase {

	private function getSite( $globalId, $domain ) {
		$mockSite = $this->getMock( Site::class );
		$mockSite->method( 'getGlobalId' )
			->will( $this->returnValue( $globalId ) );
		$mockSite->method( 'getDomain' )
			->will( $this->returnValue( $domain ) );
		$mockSite->method( 'getNavigationIds' )
			->will( $this->returnValue( [] ) );
		return $mockSite;
	}

	public function provideGetSite() {
		return [
			'google' => [ 'google.com' ],
			'commons' => [ 'commons.wikimedia.org', 'commonswiki' ],
			'enwiki' => [ 'en.wikipedia.org', 'enwiki' ],
			'dewiki' => [ 'de.wikipedia.org', 'dewiki', ],
		];
	}

	/**
	 * @dataProvider provideGetSite
	 */
	public function testGetSite( $host, $expected = null ) {
		$hashSiteStore = new HashSiteStore( [
			$this->getSite( 'enwiki', 'en.wikipedia.org' ),
			$this->getSite( 'dewiki', 'de.wikipedia.org' ),
			$this->getSite( 'commonswiki', 'commons.wikimedia.org' ),
		] );

		$lookup = new SiteTableSiteLookup( $hashSiteStore );

		$result = $lookup->getSite( $host );
		if ( $expected === null ) {
			$this->assertEquals( null, $result );
		} else {
			/** @var MediaWikiSite $result */
			$this->assertEquals( $result->getGlobalId(), $expected );
		}
	}

}
