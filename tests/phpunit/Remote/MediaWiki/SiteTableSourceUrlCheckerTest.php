<?php

namespace FileImporter\Tests\MediaWiki;

use FileImporter\Data\SourceUrl;
use FileImporter\Remote\MediaWiki\SiteTableSiteLookup;
use FileImporter\Remote\MediaWiki\SiteTableSourceUrlChecker;
use MediaWiki\Site\HashSiteStore;
use MediaWiki\Site\Site;

/**
 * @covers \FileImporter\Remote\MediaWiki\SiteTableSourceUrlChecker
 *
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class SiteTableSourceUrlCheckerTest extends \MediaWikiIntegrationTestCase {

	/**
	 * @param string[] $knownSites
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

	public static function provideCheckSourceUrl() {
		return [
			'bad target & known site' => [
				'//en.wikipedia.org',
				[ 'enwiki' => '//en.wikipedia.org/wiki' ],
				false,
			],
			'good target & no known sites' => [
				'//en.wikipedia.org/wiki/File:Foo',
				[],
				false,
			],
			'good target (path) but empty title & known site' => [
				'//en.wikipedia.org/',
				[ 'enwiki' => '//en.wikipedia.org/wiki' ],
				false,
			],
			'good target (query) but empty title & known site' => [
				'//en.wikipedia.org/w/index.php?title=',
				[ 'enwiki' => '//en.wikipedia.org/wiki' ],
				false,
			],
			// CanGetImportDetails = true
			'good target (path) & known site' => [
				'//en.wikipedia.org/wiki/File:Foo',
				[ 'enwiki' => '//en.wikipedia.org/wiki' ],
				true,
			],
			'good target (query) & known site' => [
				'//en.wikipedia.org/w/index.php?title=File:Foo',
				[ 'enwiki' => '//en.wikipedia.org/wiki' ],
				true,
			],
		];
	}

	/**
	 * @dataProvider provideCheckSourceUrl
	 */
	public function testCanGetImportDetails( string $sourceUrl, array $knownSites, bool $expected ) {
		$checker = $this->getSiteTableSourceUrlChecker( $knownSites );
		$this->assertSame( $expected, $checker->checkSourceUrl( new SourceUrl( $sourceUrl ) ) );
	}

}
