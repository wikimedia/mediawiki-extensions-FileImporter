<?php

namespace FileImporter\Remote\MediaWiki\Test;

use FileImporter\Data\SourceUrl;
use FileImporter\Remote\MediaWiki\SiteTableSiteLookup;
use FileImporter\Remote\MediaWiki\SiteTableSourceInterWikiLookup;
use MediaWikiSite;
use PHPUnit4And6Compat;
use Psr\Log\NullLogger;

/**
 * @covers \FileImporter\Remote\MediaWiki\SiteTableSourceInterWikiLookup
 *
 * @license GPL-2.0-or-later
 * @author Christoph Jauera <christoph.jauera@wikimedia.de>
 */
class SiteTableSourceInterWikiLookupTest extends \PHPUnit\Framework\TestCase {
	use PHPUnit4And6Compat;

	public function provideGetPrefix() {
		return [
			'interwiki id and language code present' => [
				'iwid',
				'qqx',
				'iwid:qqx',
			],
			'no language code configured' => [
				'iwid',
				null,
				'iwid',
			],
			'no interwiki id configured' => [
				null,
				null,
				'',
			],
		];
	}

	/**
	 * @dataProvider provideGetPrefix
	 */
	public function testGetPrefix( $iwId, $langCode, $expected ) {
		$siteTableMock = $this->createSiteTableSiteLookupMock( $iwId, $langCode );

		$sourceUrlPrefixer = new SiteTableSourceInterWikiLookup(
			$siteTableMock,
			new NullLogger()
		);

		$this->assertSame( $expected, $sourceUrlPrefixer->getPrefix(
			new SourceUrl( 'http://example.com' ) )
		);
	}

	/**
	 * @param string $iwId
	 * @param string $langCode
	 * @return SiteTableSiteLookup
	 */
	private function createSiteTableSiteLookupMock( $iwId, $langCode ) {
		$site = new MediaWikiSite();
		if ( $iwId ) {
			$site->addInterwikiId( $iwId );
		}
		$site->setLanguageCode( $langCode );

		$mock = $this->createMock( SiteTableSiteLookup::class );
		$mock->method( 'getSite' )
			->willReturn( $site );

		return $mock;
	}
}
