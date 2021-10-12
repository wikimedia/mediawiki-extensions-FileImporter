<?php

namespace FileImporter\Tests\Services;

use Config;
use FileImporter\Data\SourceUrl;
use FileImporter\Remote\MediaWiki\SiteTableSiteLookup;
use FileImporter\Services\Http\HttpRequestExecutor;
use FileImporter\Services\WikidataTemplateLookup;
use MediaWikiIntegrationTestCase;
use MWHttpRequest;
use Psr\Log\LoggerInterface;
use Site;

/**
 * @covers \FileImporter\Services\WikidataTemplateLookup
 *
 * FIXME: Integrationesque and kind of crappy.
 *
 * @license GPL-2.0-or-later
 */
class WikidataTemplateLookupTest extends MediaWikiIntegrationTestCase {

	public function testFetchNowCommonsLocalTitle_success() {
		$mockConfig = $this->createMock( Config::class );
		$mockConfig->method( 'get' )
			->willReturnCallback( static function ( $key ) {
				$data = [
					'FileImporterWikidataEntityEndpoint' => 'https://wikidata.invalid/wiki/Special:EntityData/',
					'FileImporterWikidataNowCommonsEntity' => 'Q123'
				];

				return $data[$key];
			} );

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
			->with( 'https://wikidata.invalid/wiki/Special:EntityData/Q123' )
			->willReturn( $mockResponse );

		$lookup = new WikidataTemplateLookup(
			$mockConfig,
			$mockSiteLookup,
			$mockRequestExecutor,
			$this->createMock( LoggerInterface::class )
		);

		$sourceUrl = new SourceUrl(
			'https://bat-smg.wikipedia.org/wiki/Abruozdielis:Country_house_at_sunset.jpg' );

		// make sure API will only be hit once on multiple calls
		$lookup->fetchNowCommonsLocalTitle( $sourceUrl );
		$localTitle = $lookup->fetchNowCommonsLocalTitle( $sourceUrl );

		$this->assertEquals( 'Vikitekuo', $localTitle );
	}

	public function testFetchLocalTemplateForSource_noSite() {
		$mockSiteLookup = $this->createMock( SiteTableSiteLookup::class );

		$lookup = new WikidataTemplateLookup(
			$this->createMock( Config::class ),
			$mockSiteLookup,
			$this->createMock( HttpRequestExecutor::class ),
			$this->createMock( LoggerInterface::class )
		);

		$sourceUrl = new SourceUrl(
			'https://bat-smg.wikipedia.org/wiki/Abruozdielis:Country_house_at_sunset.jpg' );
		$localTitle = $lookup->fetchNowCommonsLocalTitle( $sourceUrl );

		$this->assertNull( $localTitle );
	}

	public function testFetchLocalTemplateForSource_noSiteLink() {
		$mockConfig = $this->createMock( Config::class );
		$mockConfig->method( 'get' )
			->willReturnCallback( static function ( $key ) {
				$data = [
					'FileImporterWikidataEntityEndpoint' => 'https://wikidata.invalid/wiki/Special:EntityData/',
					'FileImporterWikidataNowCommonsEntity' => 'Q123'
				];

				return $data[$key];
			} );

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
			->with( 'https://wikidata.invalid/wiki/Special:EntityData/Q123' )
			->willReturn( $mockResponse );

		$lookup = new WikidataTemplateLookup(
			$mockConfig,
			$mockSiteLookup,
			$mockRequestExecutor,
			$this->createMock( LoggerInterface::class )
		);

		$sourceUrl = new SourceUrl(
			'https://foo.wikipedia.org/wiki/Abruozdielis:Country_house_at_sunset.jpg' );
		$localTitle = $lookup->fetchNowCommonsLocalTitle( $sourceUrl );

		$this->assertNull( $localTitle );
	}

}
