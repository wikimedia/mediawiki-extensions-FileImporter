<?php

namespace FileImporter\Tests\Services;

use FileImporter\Data\SourceUrl;
use FileImporter\Exceptions\SourceUrlException;
use FileImporter\Services\SourceSite;
use FileImporter\Services\SourceSiteLocator;

/**
 * @covers \FileImporter\Services\SourceSiteLocator
 *
 * @license GPL-2.0-or-later
 * @author Thiemo Kreuz
 */
class SourceSiteLocatorTest extends \PHPUnit\Framework\TestCase {

	public function testNoSitesGiven() {
		$url = new SourceUrl( '//wikimedia.de' );
		$locator = new SourceSiteLocator( [] );

		$this->expectException( SourceUrlException::class );
		$locator->getSourceSite( $url );
	}

	public function testUrlDoesNotMatchAnySite() {
		$url = new SourceUrl( '//wikimedia.de' );
		$site = $this->newSourceSite( false );
		$locator = new SourceSiteLocator( [ $site ] );

		$this->expectException( SourceUrlException::class );
		$locator->getSourceSite( $url );
	}

	public function testUrlDoesMatch() {
		$url = new SourceUrl( '//wikimedia.de' );
		$site = $this->newSourceSite( true );
		$locator = new SourceSiteLocator( [ $site ] );

		$this->assertSame( $site, $locator->getSourceSite( $url ) );
	}

	/**
	 * @param bool $isSourceSite
	 *
	 * @return SourceSite
	 */
	private function newSourceSite( $isSourceSite ): SourceSite {
		$site = $this->createMock( SourceSite::class );
		$site->method( 'isSourceSiteFor' )
			->willReturn( $isSourceSite );
		return $site;
	}

}
