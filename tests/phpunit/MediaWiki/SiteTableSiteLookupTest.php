<?php

namespace FileImporter\MediaWiki\Test;

use FileImporter\Remote\MediaWiki\SiteTableSiteLookup;
use HashSiteStore;
use MediaWikiSite;
use PHPUnit4And6Compat;
use Site;

/**
 * @covers \FileImporter\Remote\MediaWiki\SiteTableSiteLookup
 *
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class SiteTableSiteLookupTest extends \PHPUnit\Framework\TestCase {
	use PHPUnit4And6Compat;

	/**
	 * @param string $globalId
	 * @param string $domain
	 *
	 * @return Site
	 */
	private function getSite( $globalId, $domain ) {
		$mockSite = $this->createMock( Site::class );
		$mockSite->method( 'getGlobalId' )
			->willReturn( $globalId );
		$mockSite->method( 'getDomain' )
			->willReturn( $domain );
		$mockSite->method( 'getNavigationIds' )
			->willReturn( [] );
		return $mockSite;
	}

	public function provideGetSite() {
		return [
			'google' => [ 'google.com', null ],
			'commons' => [ 'commons.wikimedia.org', 'commonswiki' ],
			'enwiki' => [ 'en.wikipedia.org', 'enwiki' ],
			'dewiki' => [ 'de.wikipedia.org', 'dewiki', ],
		];
	}

	/**
	 * @dataProvider provideGetSite
	 */
	public function testGetSite( $host, $expected ) {
		$hashSiteStore = new HashSiteStore( [
			$this->getSite( 'enwiki', 'en.wikipedia.org' ),
			$this->getSite( 'dewiki', 'de.wikipedia.org' ),
			$this->getSite( 'commonswiki', 'commons.wikimedia.org' ),
		] );

		$lookup = new SiteTableSiteLookup( $hashSiteStore );

		$result = $lookup->getSite( $host );
		if ( $expected === null ) {
			$this->assertNull( $result );
		} else {
			/** @var MediaWikiSite $result */
			$this->assertEquals( $result->getGlobalId(), $expected );
		}
	}

}
