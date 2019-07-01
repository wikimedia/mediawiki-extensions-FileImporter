<?php

namespace FileImporter\Tests\Remote\MediaWiki;

use FileImporter\Data\ImportDetails;
use FileImporter\Data\ImportPlan;
use FileImporter\Data\SourceUrl;
use FileImporter\Remote\MediaWiki\NowCommonsHelperPostImportHandler;
use FileImporter\Services\WikidataTemplateLookup;
use MediaWikiUnitTestCase;

/**
 * @covers \FileImporter\Remote\MediaWiki\NowCommonsHelperPostImportHandler
 *
 * @license GPL-2.0-or-later
 * @author Christoph Jauera <christoph.jauera@wikimedia.de>
 */
class NowCommonsHelperPostImportHandlerTest extends MediaWikiUnitTestCase {

	const URL = 'http://w.invalid';
	const TITLE = 'FilePageTitle';

	public function provideExecute() {
		yield [ 'TestTemplate',	'fileimporter-add-specific-template' ];
		yield [ null, 'fileimporter-add-unknown-template' ];
	}

	/**
	 * @dataProvider provideExecute
	 */
	public function testExecute( $templateResult, $expected ) {
		$sourceUrlMock = $this->createMock( SourceUrl::class );
		$sourceUrlMock
			->method( 'getUrl' )
			->willReturn( self::URL );

		$importDetailsMock = $this->createMock( ImportDetails::class );
		$importDetailsMock
			->method( 'getSourceUrl' )
			->willReturn( $sourceUrlMock );

		$importPlanMock = $this->createMock( ImportPlan::class );
		$importPlanMock
			->method( 'getDetails' )
			->willReturn( $importDetailsMock );
		$importPlanMock
			->method( 'getTitleText' )
			->willReturn( self::TITLE );

		$importHandler = new NowCommonsHelperPostImportHandler(
			$this->createWikidataTemplateLookup( $sourceUrlMock, $templateResult )
		);

		$status = $this->createStatus( $templateResult, $expected );

		$this->assertEquals( $status, $importHandler->execute( $importPlanMock, new \User() ) );
	}

	private function createStatus( $templateName, $expectedMessage ) {
		$params = [ self::URL ];
		if ( $templateName ) {
			$params[] = $templateName;
			$params[] = self::TITLE;
		}

		return \StatusValue::newGood(
			new \Message(
				$expectedMessage,
				$params
			)
		);
	}

	/**
	 * @param SourceUrl $sourceUrl
	 * @param string|null $templateResult
	 * @return WikidataTemplateLookup
	 */
	private function createWikidataTemplateLookup( $sourceUrl, $templateResult ) {
		$mock = $this->createMock( WikidataTemplateLookup::class );
		$mock
			->method( 'fetchNowCommonsLocalTitle' )
			->with( $sourceUrl )
			->willReturn( $templateResult );

		return $mock;
	}

}
