<?php

namespace FileImporter\Tests\Services;

use FileImporter\Data\SourceUrl;
use FileImporter\Exceptions\SourceUrlException;
use FileImporter\Services\SourceSite;
use FileImporter\Services\SourceSiteLocator;
use PHPUnit4And6Compat;

/**
 * @covers \FileImporter\Services\SourceSiteLocator
 *
 * @license GPL-2.0-or-later
 * @author Thiemo Kreuz
 */
class SourceSiteLocatorTest extends \PHPUnit\Framework\TestCase {
	use PHPUnit4And6Compat;

	public function testNoSitesGiven() {
		$url = new SourceUrl( '//wikimedia.de' );
		$locator = new SourceSiteLocator( [] );

		$this->setExpectedException( SourceUrlException::class );
		$locator->getSourceSite( $url );
	}

	public function testUrlDoesNotMatchAnySite() {
		$url = new SourceUrl( '//wikimedia.de' );
		$site = $this->newSourceSite( false );
		$locator = new SourceSiteLocator( [ $site ] );

		$this->setExpectedException( SourceUrlException::class );
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
	private function newSourceSite( $isSourceSite ) {
		$site = $this->createMock( SourceSite::class );
		$site->method( 'isSourceSiteFor' )
			->willReturn( $isSourceSite );
		return $site;
	}

}
