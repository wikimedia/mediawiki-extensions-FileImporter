<?php
namespace FileImporter\MediaWiki\Test;

use FileImporter\Generic\HttpRequestExecutor;
use FileImporter\Generic\TargetUrl;
use FileImporter\MediaWiki\ApiDetailRetriever;
use FileImporter\MediaWiki\HttpApiLookup;
use FileImporter\MediaWiki\SiteTableSiteLookup;
use HashSiteStore;
use PHPUnit_Framework_TestCase;
use Site;

class ApiDetailRetrieverTest extends PHPUnit_Framework_TestCase {

	private function getApiDetailRetriever( $knownSites = [] ) {
		/** @var HttpApiLookup $httpApiLookup */
		$httpApiLookup = $this->getMockBuilder( HttpApiLookup::class )
			->disableOriginalConstructor()
			->getMock();
		/** @var HttpRequestExecutor $httpRequestExecutor */
		$httpRequestExecutor = $this->getMockBuilder( HttpRequestExecutor::class )
			->disableOriginalConstructor()
			->getMock();

		$sites = [];
		foreach ( $knownSites as $siteCode => $linkPath ) {
			$site = new Site();
			$site->setGlobalId( $siteCode );
			$site->setLinkPath( $linkPath );
			$sites[] = $site;
		}

		return new ApiDetailRetriever(
			new SiteTableSiteLookup( new HashSiteStore( $sites ) ),
			$httpApiLookup,
			$httpRequestExecutor
		);
	}

	public function provideCanGetImportDetails() {
		return [
			// CanGetImportDetails = false
			'bad target & no known sites' => [
				new TargetUrl( 'foo' ),
				[],
				false
			],
			'bad target & known site' => [
				new TargetUrl( 'http://en.wikipedia.org' ),
				[
					'enwiki' => 'http://en.wikipedia.org/wiki',
				],
				false,
			],
			'good target & no known sites' => [
				new TargetUrl( 'http://en.wikipedia.org/wiki/File:Foo' ),
				[],
				false,
			],
			'good target (path) but empty title & known site' => [
				new TargetUrl( 'http://en.wikipedia.org/' ),
				[
					'enwiki' => 'http://en.wikipedia.org/wiki',
				],
				false,
			],
			'good target (query) but empty title & known site' => [
				new TargetUrl( 'http://en.wikipedia.org/w/index.php?title=' ),
				[
					'enwiki' => 'http://en.wikipedia.org/wiki',
				],
				false,
			],
			// CanGetImportDetails = true
			'good target (path) & known site' => [
				new TargetUrl( 'http://en.wikipedia.org/wiki/File:Foo' ),
				[
					'enwiki' => 'http://en.wikipedia.org/wiki',
				],
				true,
			],
			'good target (query) & known site' => [
				new TargetUrl( 'http://en.wikipedia.org/w/index.php?title=File:Foo' ),
				[
					'enwiki' => 'http://en.wikipedia.org/wiki',
				],
				true,
			],
		];
	}

	/**
	 * @dataProvider provideCanGetImportDetails
	 * @param TargetUrl $targetUrl
	 * @param array $knownSites
	 * @param bool $expected
	 */
	public function testCanGetImportDetails( TargetUrl $targetUrl, array $knownSites, $expected ) {
		$service = $this->getApiDetailRetriever( $knownSites );
		$this->assertEquals( $expected, $service->canGetImportDetails( $targetUrl ) );
	}

}
