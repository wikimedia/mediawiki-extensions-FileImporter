<?php

namespace FileImporter\Tests\Services;

use FileImporter\Data\SourceUrl;
use FileImporter\Remote\MediaWiki\SiteTableSiteLookup;
use FileImporter\Services\Http\HttpRequestExecutor;
use FileImporter\Services\WikidataTemplateLookup;
use MediaWiki\Config\Config;
use MediaWiki\Config\HashConfig;
use MediaWiki\Site\Site;
use MediaWikiIntegrationTestCase;
use MWHttpRequest;
use Psr\Log\NullLogger;

/**
 * @covers \FileImporter\Services\WikidataTemplateLookup
 *
 * FIXME: Integrationesque and kind of crappy.
 *
 * @license GPL-2.0-or-later
 */
class WikidataTemplateLookupTest extends MediaWikiIntegrationTestCase {

	public function testFetchNowCommonsLocalTitle_success() {
		$mockSite = $this->createMock( Site::class );
		$mockSite->method( 'getGlobalId' )
			->willReturn( 'bat_smgwiki' );
		$mockSiteLookup = $this->createMock( SiteTableSiteLookup::class );
		$mockSiteLookup->method( 'getSite' )
			->willReturn( $mockSite );

		$content = file_get_contents( __DIR__ . '/../../data/NowCommons_entity.json' );
		$mockResponse = $this->createMock( MWHttpRequest::class );
		$mockResponse
			->expects( $this->once() )
			->method( 'getContent' )
			->willReturn( $content );
		$mockRequestExecutor = $this->createMock( HttpRequestExecutor::class );
		$mockRequestExecutor
			->expects( $this->once() )
			->method( 'execute' )
			->with( '//wikidata.invalid/wiki/Special:EntityData/Q123' )
			->willReturn( $mockResponse );

		$lookup = new WikidataTemplateLookup(
			$this->getConfig(),
			$mockSiteLookup,
			$mockRequestExecutor,
			new NullLogger()
		);

		$sourceUrl = new SourceUrl(
			'//bat-smg.wikipedia.org/wiki/Abruozdielis:Country_house_at_sunset.jpg' );

		// make sure API will only be hit once on multiple calls
		$lookup->fetchNowCommonsLocalTitle( $sourceUrl );
		$localTitle = $lookup->fetchNowCommonsLocalTitle( $sourceUrl );

		$this->assertEquals( 'Vikitekuo', $localTitle );
	}

	public function testFetchLocalTemplateForSource_noSite() {
		$mockSiteLookup = $this->createMock( SiteTableSiteLookup::class );

		$lookup = new WikidataTemplateLookup(
			$this->getConfig(),
			$mockSiteLookup,
			$this->createNoOpMock( HttpRequestExecutor::class ),
			new NullLogger()
		);

		$sourceUrl = new SourceUrl(
			'//bat-smg.wikipedia.org/wiki/Abruozdielis:Country_house_at_sunset.jpg' );
		$localTitle = $lookup->fetchNowCommonsLocalTitle( $sourceUrl );

		$this->assertNull( $localTitle );
	}

	public function testFetchLocalTemplateForSource_noSiteLink() {
		$mockSite = $this->createMock( Site::class );
		$mockSite->method( 'getGlobalId' )
			->willReturn( 'foowiki' );
		$mockSiteLookup = $this->createMock( SiteTableSiteLookup::class );
		$mockSiteLookup->method( 'getSite' )
			->willReturn( $mockSite );

		$content = file_get_contents( __DIR__ . '/../../data/NowCommons_entity.json' );
		$mockResponse = $this->createMock( MWHttpRequest::class );
		$mockResponse->method( 'getContent' )
			->willReturn( $content );
		$mockRequestExecutor = $this->createMock( HttpRequestExecutor::class );
		$mockRequestExecutor->method( 'execute' )
			->with( '//wikidata.invalid/wiki/Special:EntityData/Q123' )
			->willReturn( $mockResponse );

		$lookup = new WikidataTemplateLookup(
			$this->getConfig(),
			$mockSiteLookup,
			$mockRequestExecutor,
			new NullLogger()
		);

		$sourceUrl = new SourceUrl(
			'//foo.wikipedia.org/wiki/Abruozdielis:Country_house_at_sunset.jpg' );
		$localTitle = $lookup->fetchNowCommonsLocalTitle( $sourceUrl );

		$this->assertNull( $localTitle );
	}

	private function getConfig(): Config {
		return new HashConfig( [
			'FileImporterWikidataEntityEndpoint' => '//wikidata.invalid/wiki/Special:EntityData/',
			'FileImporterWikidataNowCommonsEntity' => 'Q123',
		] );
	}

}
