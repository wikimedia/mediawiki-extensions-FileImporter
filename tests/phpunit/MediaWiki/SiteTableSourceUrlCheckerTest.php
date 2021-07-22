<?php

namespace FileImporter\Tests\MediaWiki;

use FileImporter\Data\SourceUrl;
use FileImporter\Remote\MediaWiki\SiteTableSiteLookup;
use FileImporter\Remote\MediaWiki\SiteTableSourceUrlChecker;
use HashSiteStore;
use Site;

/**
 * @covers \FileImporter\Remote\MediaWiki\SiteTableSourceUrlChecker
 *
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class SiteTableSourceUrlCheckerTest extends \PHPUnit\Framework\TestCase {

	/**
	 * @param string[] $knownSites
	 *
	 * @return SiteTableSourceUrlChecker
	 */
	private function getSiteTableSourceUrlChecker( array $knownSites ): SiteTableSourceUrlChecker {
		$sites = [];
		foreach ( $knownSites as $siteCode => $linkPath ) {
			$site = new Site();
			$site->setGlobalId( $siteCode );
			$site->setLinkPath( $linkPath );
			$sites[] = $site;
		}

		return new SiteTableSourceUrlChecker(
			new SiteTableSiteLookup( new HashSiteStore( $sites ) )
		);
	}

	public function provideCheckSourceUrl() {
		return [
			'bad target & known site' => [
				new SourceUrl( 'http://en.wikipedia.org' ),
				[ 'enwiki' => 'http://en.wikipedia.org/wiki' ],
				false,
			],
			'good target & no known sites' => [
				new SourceUrl( 'http://en.wikipedia.org/wiki/File:Foo' ),
				[],
				false,
			],
			'good target (path) but empty title & known site' => [
				new SourceUrl( 'http://en.wikipedia.org/' ),
				[ 'enwiki' => 'http://en.wikipedia.org/wiki' ],
				false,
			],
			'good target (query) but empty title & known site' => [
				new SourceUrl( 'http://en.wikipedia.org/w/index.php?title=' ),
				[ 'enwiki' => 'http://en.wikipedia.org/wiki' ],
				false,
			],
			// CanGetImportDetails = true
			'good target (path) & known site' => [
				new SourceUrl( 'http://en.wikipedia.org/wiki/File:Foo' ),
				[ 'enwiki' => 'http://en.wikipedia.org/wiki' ],
				true,
			],
			'good target (query) & known site' => [
				new SourceUrl( 'http://en.wikipedia.org/w/index.php?title=File:Foo' ),
				[ 'enwiki' => 'http://en.wikipedia.org/wiki' ],
				true,
			],
		];
	}

	/**
	 * @dataProvider provideCheckSourceUrl
	 */
	public function testCanGetImportDetails( SourceUrl $sourceUrl, array $knownSites, $expected ) {
		$checker = $this->getSiteTableSourceUrlChecker( $knownSites );
		$this->assertSame( $expected, $checker->checkSourceUrl( $sourceUrl ) );
	}

}
