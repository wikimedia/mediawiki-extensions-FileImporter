<?php

namespace FileImporter\Tests\Remote\MediaWiki;

use FileImporter\Data\ImportDetails;
use FileImporter\Data\ImportPlan;
use FileImporter\Data\SourceUrl;
use FileImporter\Remote\MediaWiki\CentralAuthPostImportHandler;
use FileImporter\Remote\MediaWiki\RemoteApiActionExecutor;
use FileImporter\Services\WikidataTemplateLookup;
use MediaWikiTestCase;
use Message;
use Psr\Log\NullLogger;
use StatusValue;
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

		$url = 'http://w.invalid/w/foo' . mt_rand();
		$this->assertEquals(
			StatusValue::newGood(
				new Message( 'fileimporter-add-unknown-template', [ $url ] )
			),
			$postImportHandler->execute(
				$this->createImportPlanMock( false, false, $url ),
				$this->createMock( User::class )
			)
		);
	}

	public function testExecute_remoteEditSucceeds() {
		$url = 'http://w.invalid/w/foo' . mt_rand();
		$mockImportPlan = $this->createImportPlanMock( true, false, $url );
		$mockUser = $this->createMock( User::class );

		$mockRemoteAction = $this->createMock( RemoteApiActionExecutor::class );
		$mockRemoteAction->expects( $this->once() )
			->method( 'executeEditAction' )
			->with(
				$this->anything(),
				$this->equalTo( $mockUser ),
				$this->equalTo( [
					'title' => 'TestTitle',
					'appendtext' => "\n{{TestNowCommons|TestTitle2}}",
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

		$this->assertEquals(
			StatusValue::newGood(
				new Message( 'fileimporter-imported-success-banner' )
			),
			$postImportHandler->execute( $mockImportPlan, $mockUser )
		);
	}

	public function testExecute_remoteEditFails() {
		$url = 'http://w.invalid/w/foo' . mt_rand();
		$mockImportPlan = $mockImportPlan = $this->createImportPlanMock( true, false, $url );
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

		$expectedStatus = StatusValue::newGood(
			new Message(
				'fileimporter-add-specific-template',
				[ $url, 'TestNowCommons', 'TestTitle2' ]
			)
		);
		$expectedStatus->warning( new Message( 'fileimporter-cleanup-failed' ) );
		$this->assertEquals(
			$expectedStatus,
			$postImportHandler->execute( $mockImportPlan, $mockUser )
		);
	}

	public function testExecute_remoteDeleteSucceeds() {
		$url = 'http://w.invalid/w/foo' . mt_rand();
		$mockImportPlan = $this->createImportPlanMock( false, true, $url );
		$mockUser = $this->createMock( User::class );

		$mockRemoteAction = $this->createMock( RemoteApiActionExecutor::class );
		$mockRemoteAction->expects( $this->once() )
			->method( 'executeDeleteAction' )
			->with(
				$this->anything(),
				$this->equalTo( $mockUser ),
				$this->equalTo( [
					'title' => 'TestTitle',
					'reason' => '(fileimporter-delete-summary)',
				] )
			)
			// FIXME: not a realistic result, but we don't care yet.
			->willReturn( true );

		$postImportHandler = new CentralAuthPostImportHandler(
			$mockRemoteAction,
			$this->createMock( WikidataTemplateLookup::class ),
			new NullLogger()
		);

		$this->assertEquals(
			StatusValue::newGood(
				new Message( 'fileimporter-imported-success-banner' )
			),
			$postImportHandler->execute( $mockImportPlan, $mockUser )
		);
	}

	public function testExecute_remoteDeleteFails() {
		$host = 'w.' . mt_rand() . '.invalid';
		$url = 'http://w.invalid/w/foo' . mt_rand();
		$mockImportPlan = $mockImportPlan = $this->createImportPlanMock( false, true, $url, $host );
		$mockUser = $this->createMock( User::class );

		$mockRemoteAction = $this->createMock( RemoteApiActionExecutor::class );
		$mockRemoteAction->expects( $this->once() )
			->method( 'executeDeleteAction' )
			->willReturn( null );

		$postImportHandler = new CentralAuthPostImportHandler(
			$mockRemoteAction,
			$this->createMock( WikidataTemplateLookup::class ),
			new NullLogger()
		);

		$expectedStatus = StatusValue::newGood(
			new Message( 'fileimporter-imported-success-banner' )
		);
		$expectedStatus->warning( 'fileimporter-delete-failed', $host, $url );
		$this->assertEquals(
			$expectedStatus,
			$postImportHandler->execute( $mockImportPlan, $mockUser )
		);
	}

	private function createImportPlanMock( $autoCleanup, $autoDelete, $url = '', $host = '' ) {
		$mockTitle = $this->createMock( Title::class );
		$mockTitle->method( 'getPrefixedText' )
			->willReturn( 'TestTitle' );

		$mockSourceUrl = $this->createMock( SourceUrl::class );
		$mockSourceUrl->method( 'getHost' )
			->willReturn( $host );
		$mockSourceUrl->method( 'getUrl' )
			->willReturn( $url );
		$mockDetails = $this->createMock( ImportDetails::class );
		$mockDetails->method( 'getSourceUrl' )
			->willReturn( $mockSourceUrl );
		$mockDetails->method( 'getPageLanguage' )
			->willReturn( 'qqx' );
		$mockImportPlan = $this->createMock( ImportPlan::class );
		$mockImportPlan->method( 'getAutomateSourceWikiCleanUp' )
			->willReturn( $autoCleanup );
		$mockImportPlan->method( 'getAutomateSourceWikiDelete' )
			->willReturn( $autoDelete );
		$mockImportPlan->method( 'getDetails' )
			->willReturn( $mockDetails );
		$mockImportPlan->method( 'getTitle' )
			->willReturn( $mockTitle );
		$mockImportPlan->method( 'getTitleText' )
			->willReturn( 'TestTitle2' );

		return $mockImportPlan;
	}

}
