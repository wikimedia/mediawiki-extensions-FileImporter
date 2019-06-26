<?php

namespace FileImporter\Tests\Remote\MediaWiki;

use FileImporter\Data\ImportDetails;
use FileImporter\Data\ImportPlan;
use FileImporter\Data\SourceUrl;
use FileImporter\Remote\MediaWiki\CentralAuthPostImportHandler;
use FileImporter\Remote\MediaWiki\RemoteApiActionExecutor;
use FileImporter\Services\WikidataTemplateLookup;
use MediaWikiTestCase;
use Psr\Log\NullLogger;
use Title;
use User;

/**
 * @covers \FileImporter\Remote\MediaWiki\CentralAuthPostImportHandler
 */
class CentralAuthPostImportHandlerTest extends MediaWikiTestCase {

	public function testExecute_noCleanupRequested() {
		$mockTemplateLookup = $this->createMock( WikidataTemplateLookup::class );
		$mockTemplateLookup->expects( $this->once() )
			->method( 'fetchNowCommonsLocalTitle' )
			->willReturn( null );

		$postImportHandler = new CentralAuthPostImportHandler(
			$this->createMock( RemoteApiActionExecutor::class ),
			$mockTemplateLookup,
			new NullLogger()
		);

		// TODO assert return Status object
		$postImportHandler->execute(
			$this->createImportPlanMock( false ),
			$this->createMock( User::class )
		);
	}

	public function testExecute_remoteActionSucceeds() {
		$mockImportPlan = $this->createImportPlanMock( true );
		$mockUser = $this->createMock( User::class );

		$mockRemoteAction = $this->createMock( RemoteApiActionExecutor::class );
		$mockRemoteAction->expects( $this->once() )
			->method( 'executeEditAction' )
			->with(
				$this->anything(),
				$this->equalTo( $mockUser ),
				$this->equalTo( [
					'title' => 'TestTitle',
					'appendtext' => '{{TestNowCommons|TestTitle2}}',
					'summary' => '(fileimporter-cleanup-summary)',
				] )
			)
			// FIXME: not a realistic result, but we don't care yet.
			->willReturn( true );
		$mockTemplateLookup = $this->createMock( WikidataTemplateLookup::class );
		$mockTemplateLookup->method( 'fetchNowCommonsLocalTitle' )
			->willReturn( 'TestNowCommons' );

		$postImportHandler = new CentralAuthPostImportHandler(
			$mockRemoteAction,
			$mockTemplateLookup,
			new NullLogger()
		);

		// TODO assert return Status object
		$postImportHandler->execute( $mockImportPlan, $mockUser );
	}

	public function testExecute_remoteActionFails() {
		$mockImportPlan = $mockImportPlan = $this->createImportPlanMock( true );
		$mockUser = $this->createMock( User::class );

		$mockRemoteAction = $this->createMock( RemoteApiActionExecutor::class );
		$mockRemoteAction->expects( $this->once() )
			->method( 'executeEditAction' )
			->willReturn( null );
		$mockTemplateLookup = $this->createMock( WikidataTemplateLookup::class );
		$mockTemplateLookup->method( 'fetchNowCommonsLocalTitle' )
			->willReturn( 'TestNowCommons' );

		$postImportHandler = new CentralAuthPostImportHandler(
			$mockRemoteAction,
			$mockTemplateLookup,
			new NullLogger()
		);

		// TODO assert return Status object
		$postImportHandler->execute( $mockImportPlan, $mockUser );
	}

	private function createImportPlanMock( $autoCleanup ) {
		$mockTitle = $this->createMock( Title::class );
		$mockTitle->method( 'getPrefixedText' )
			->willReturn( 'TestTitle' );

		$mockDetails = $this->createMock( ImportDetails::class );
		$mockDetails->method( 'getSourceUrl' )
			->willReturn( $this->createMock( SourceUrl::class ) );
		$mockDetails->method( 'getPageLanguage' )
			->willReturn( 'qqx' );
		$mockImportPlan = $this->createMock( ImportPlan::class );
		$mockImportPlan->method( 'getAutomateSourceWikiCleanUp' )
			->willReturn( $autoCleanup );
		$mockImportPlan->method( 'getDetails' )
			->willReturn( $mockDetails );
		$mockImportPlan->method( 'getTitle' )
			->willReturn( $mockTitle );
		$mockImportPlan->method( 'getTitleText' )
			->willReturn( 'TestTitle2' );

		return $mockImportPlan;
	}

}
