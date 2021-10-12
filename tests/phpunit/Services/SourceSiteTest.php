<?php

namespace FileImporter\Tests\Services;

use FileImporter\Data\ImportDetails;
use FileImporter\Data\ImportPlan;
use FileImporter\Data\SourceUrl;
use FileImporter\Interfaces\DetailRetriever;
use FileImporter\Interfaces\ImportTitleChecker;
use FileImporter\Interfaces\LinkPrefixLookup;
use FileImporter\Interfaces\SourceUrlChecker;
use FileImporter\Remote\MediaWiki\RemoteSourceFileEditDeleteAction;
use FileImporter\Services\SourceSite;
use FileImporter\Services\SourceUrlNormalizer;

/**
 * @covers \FileImporter\Services\SourceSite
 *
 * @license GPL-2.0-or-later
 */
class SourceSiteTest extends \MediaWikiIntegrationTestCase {

	public function testServiceWiring() {
		$sourceUrl = new SourceUrl( '//w.invalid' );
		$sourceUrlNormalizer = $this->createMock( SourceUrlNormalizer::class );
		$sourceUrlNormalizer->expects( $this->exactly( 3 ) )
			->method( 'normalize' )
			->with( $sourceUrl )
			->willReturnArgument( 0 );

		$sourceUrlChecker = $this->createMock( SourceUrlChecker::class );
		$sourceUrlChecker->expects( $this->once() )
			->method( 'checkSourceUrl' )
			->with( $sourceUrl )
			->willReturn( true );

		$importDetails = $this->createMock( ImportDetails::class );
		$detailRetriever = $this->createMock( DetailRetriever::class );
		$detailRetriever->expects( $this->once() )
			->method( 'getImportDetails' )
			->with( $sourceUrl )
			->willReturn( $importDetails );

		$importTitleChecker = $this->createMock( ImportTitleChecker::class );

		$linkPrefixLookup = $this->createMock( LinkPrefixLookup::class );
		$linkPrefixLookup->expects( $this->once() )
			->method( 'getPrefix' )
			->with( $sourceUrl )
			->willReturn( 'PREFIX' );

		$postImportHandler = $this->createMock( RemoteSourceFileEditDeleteAction::class );
		$postImportHandler->expects( $this->once() )
			->method( 'execute' );

		$site = new SourceSite(
			$sourceUrlChecker,
			$detailRetriever,
			$importTitleChecker,
			$sourceUrlNormalizer,
			$linkPrefixLookup,
			$postImportHandler
		);

		$site->getPostImportHandler()->execute(
			$this->createMock( ImportPlan::class ),
			$this->createMock( \User::class )
		);

		$this->assertTrue( $site->isSourceSiteFor( $sourceUrl ) );
		$this->assertSame( $importDetails, $site->retrieveImportDetails( $sourceUrl ) );
		$this->assertSame( $importTitleChecker, $site->getImportTitleChecker() );
		$this->assertSame( 'PREFIX', $site->getLinkPrefix( $sourceUrl ) );
	}

}
