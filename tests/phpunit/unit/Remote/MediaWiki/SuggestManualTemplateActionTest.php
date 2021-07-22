<?php

namespace FileImporter\Tests\Remote\MediaWiki;

use FileImporter\Data\ImportDetails;
use FileImporter\Data\ImportPlan;
use FileImporter\Data\SourceUrl;
use FileImporter\Remote\MediaWiki\SuggestManualTemplateAction;
use FileImporter\Services\WikidataTemplateLookup;
use StatusValue;
use Title;

/**
 * @covers \FileImporter\Remote\MediaWiki\SuggestManualTemplateAction
 *
 * @license GPL-2.0-or-later
 * @author Christoph Jauera <christoph.jauera@wikimedia.de>
 */
class SuggestManualTemplateActionTest extends \MediaWikiUnitTestCase {

	private const URL = 'http://w.invalid';
	private const TITLE = 'FilePageTitle';

	public function provideExecute() {
		yield [ 'TestTemplate', 'fileimporter-add-specific-template' ];
		yield [ null, 'fileimporter-add-unknown-template' ];
	}

	/**
	 * @dataProvider provideExecute
	 */
	public function testExecute( ?string $templateResult, string $expected ) {
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
			->method( 'getTitle' )
			->willReturn( Title::makeTitle( NS_FILE, self::TITLE ) );

		$importHandler = new SuggestManualTemplateAction(
			$this->createWikidataTemplateLookup( $sourceUrlMock, $templateResult )
		);

		$status = $this->createStatus( $templateResult, $expected );

		$this->assertEquals( $status, $importHandler->execute( $importPlanMock, new \User() ) );
	}

	private function createStatus( ?string $templateName, string $expectedMessage ): StatusValue {
		$messageSpecifier = [ $expectedMessage, self::URL ];
		if ( $templateName ) {
			$messageSpecifier[] = $templateName;
			$messageSpecifier[] = self::TITLE;
		}

		return StatusValue::newGood( $messageSpecifier );
	}

	/**
	 * @param SourceUrl $sourceUrl
	 * @param string|null $templateResult
	 * @return WikidataTemplateLookup
	 */
	private function createWikidataTemplateLookup(
		SourceUrl $sourceUrl,
		?string $templateResult
	): WikidataTemplateLookup {
		$mock = $this->createMock( WikidataTemplateLookup::class );
		$mock
			->method( 'fetchNowCommonsLocalTitle' )
			->with( $sourceUrl )
			->willReturn( $templateResult );

		return $mock;
	}

}
