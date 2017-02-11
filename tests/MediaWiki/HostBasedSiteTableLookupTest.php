<?php

namespace FileImporter\MediaWiki\Test;

use FileImporter\MediaWiki\HostBasedSiteTableLookup;
use HashSiteStore;
use MediaWikiSite;
use PHPUnit_Framework_TestCase;
use Site;

class HostBasedSiteTableLookupTest extends PHPUnit_Framework_TestCase {

	private function getSite( $globalId, $domain ) {
		$mockSite = $this->getMock( Site::class );
		$mockSite->expects( $this->any() )
			->method( 'getGlobalId' )
			->will( $this->returnValue( $globalId ) );
		$mockSite->expects( $this->any() )
			->method( 'getDomain' )
			->will( $this->returnValue( $domain ) );
		$mockSite->expects( $this->any() )
			->method( 'getNavigationIds' )
			->will( $this->returnValue( [] ) );
		return $mockSite;
	}

	public function provideGetSite() {
		return [
			'google' => [ 'google.com' ],
			'commons' => [ 'commons.wikimedia.org', 'commonswiki' ],
			'enwiki http' => [ 'en.wikipedia.org', 'enwiki' ],
			'enwiki https' => [ 'en.wikipedia.org', 'enwiki' ],
			'dewiki index.php' => [ 'de.wikipedia.org', 'dewiki', ],
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

		$lookup = new HostBasedSiteTableLookup( $hashSiteStore );

		$result = $lookup->getSite( $host );
		if ( $expected === null ) {
			$this->assertEquals( null, $result );
		} else {
			/** @var MediaWikiSite $result */
			$this->assertEquals( $result->getGlobalId(), $expected );
		}
	}

}
