<?php

namespace FileImporter\Test;

use FileImporter\UrlBasedSiteLookup;
use HashSiteStore;
use MediaWikiSite;
use PHPUnit_Framework_TestCase;
use Site;

class UrlBasedSiteLookupTest extends PHPUnit_Framework_TestCase {

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
			[ 'http://google.com' ],
			'commons' => [ 'http://commons.wikimedia.org/wiki/File:Foo', 'commonswiki' ],
			'enwiki http' => [ 'http://en.wikipedia.org/wiki/File:Foo', 'enwiki' ],
			'enwiki https' => [ 'https://en.wikipedia.org/wiki/File:Foo', 'enwiki' ],
			'dewiki index.php' => [
				'https://de.wikipedia.org/w/index.php?title=File:Foo', 'dewiki',
			],
		];
	}

	/**
	 * @dataProvider provideGetSite
	 */
	public function testGetSite( $url, $expected = null ) {
		$hashSiteStore = new HashSiteStore( [
			$this->getSite( 'enwiki', 'en.wikipedia.org' ),
			$this->getSite( 'dewiki', 'de.wikipedia.org' ),
			$this->getSite( 'commonswiki', 'commons.wikimedia.org' ),
		] );

		$store = new UrlBasedSiteLookup( $hashSiteStore );

		$result = $store->getSite( wfParseUrl( $url ) );
		if ( $expected === null ) {
			$this->assertEquals( null, $result );
		} else {
			/** @var MediaWikiSite $result */
			$this->assertEquals( $result->getGlobalId(), $expected );
		}
	}

}
