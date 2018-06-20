<?php

namespace FileImporter\MediaWiki\Test;

use FileImporter\Data\SourceUrl;
use FileImporter\Remote\MediaWiki\SiteTableSiteLookup;
use HashSiteStore;
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
	 * @param string $linkPath
	 *
	 * @return Site
	 */
	private function getSite( $globalId, $linkPath ) {
		$site = new Site();
		$site->setGlobalId( $globalId );
		$site->setLinkPath( $linkPath );
		return $site;
	}

	public function provideGetSite() {
		return [
			'google' => [ '//google.com', null ],
			'commons' => [ '//commons.wikimedia.org', 'commonswiki' ],
			'enwiki' => [ '//en.wikipedia.org', 'enwiki' ],
			'dewiki' => [ '//de.wikipedia.org', 'dewiki', ],
			'test1' => [ '//example.com/test1/', null, ],
			'test2' => [ '//example.com/test2/', null, ],
		];
	}

	/**
	 * @dataProvider provideGetSite
	 */
	public function testGetSite( $url, $expected ) {
		$hashSiteStore = new HashSiteStore( [
			$this->getSite( 'enwiki', 'https://en.wikipedia.org/wiki/$1' ),
			$this->getSite( 'dewiki', 'https://de.wikipedia.org/wiki/$1' ),
			$this->getSite( 'commonswiki', 'https://commons.wikimedia.org/wiki/$1' ),
			$this->getSite( 'test1', 'https://example.com/test1/$1' ),
			$this->getSite( 'test2', 'https://example.com/test2/$1' ),
		] );

		$lookup = new SiteTableSiteLookup( $hashSiteStore );

		$result = $lookup->getSite( new SourceUrl( $url ) );
		if ( $expected === null ) {
			$this->assertNull( $result );
		} else {
			$this->assertSame( $result->getGlobalId(), $expected );
		}
	}

}
