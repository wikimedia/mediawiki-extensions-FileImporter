<?php

namespace FileImporter\Tests\MediaWiki;

use FileImporter\Data\SourceUrl;
use FileImporter\Remote\MediaWiki\SiteTableSiteLookup;
use MediaWiki\Site\HashSiteStore;
use MediaWiki\Site\Site;

/**
 * @covers \FileImporter\Remote\MediaWiki\SiteTableSiteLookup
 *
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class SiteTableSiteLookupTest extends \MediaWikiIntegrationTestCase {

	private function getSite( string $globalId, string $linkPath ): Site {
		$site = new Site();
		$site->setGlobalId( $globalId );
		$site->setLinkPath( $linkPath );
		return $site;
	}

	public static function provideGetSite() {
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
	public function testGetSite( string $url, ?string $expected ) {
		$hashSiteStore = new HashSiteStore( [
			$this->getSite( 'enwiki', '//en.wikipedia.org/wiki/$1' ),
			$this->getSite( 'dewiki', '//de.wikipedia.org/wiki/$1' ),
			$this->getSite( 'commonswiki', '//commons.wikimedia.org/wiki/$1' ),
			$this->getSite( 'test1', '//example.com/test1/$1' ),
			$this->getSite( 'test2', '//example.com/test2/$1' ),
		] );

		$lookup = new SiteTableSiteLookup( $hashSiteStore );

		$site = $lookup->getSite( new SourceUrl( $url ) );
		$siteId = $site ? $site->getGlobalId() : null;
		$this->assertSame( $expected, $siteId, '1st, uncached call' );

		$site = $lookup->getSite( new SourceUrl( $url ) );
		$siteId = $site ? $site->getGlobalId() : null;
		$this->assertSame( $expected, $siteId, '2nd, cached call' );
	}

}
