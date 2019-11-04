<?php

namespace FileImporter\Tests\Remote\MediaWiki;

use FileImporter\Data\ImportDetails;
use FileImporter\Data\ImportPlan;
use FileImporter\Data\SourceUrl;
use FileImporter\Interfaces\PostImportHandler;
use FileImporter\Remote\MediaWiki\CentralAuthPostImportHandler;
use FileImporter\Remote\MediaWiki\RemoteApiActionExecutor;
use FileImporter\Services\WikidataTemplateLookup;
use MediaWikiTestCase;
use StatusValue;
use Title;

/**
 * @covers \FileImporter\Remote\MediaWiki\CentralAuthPostImportHandler
 */
class CentralAuthPostImportHandlerTest extends MediaWikiTestCase {

	public function testExecute_noCleanupRequested() {
		$fallbackHandler = $this->createMock( PostImportHandler::class );
		$fallbackHandler->expects( $this->once() )
			->method( 'execute' )
			->willReturn( StatusValue::newGood() );

		$mockTemplateLookup = $this->createMock( WikidataTemplateLookup::class );
		$mockTemplateLookup->expects( $this->never() )
			->method( 'fetchNowCommonsLocalTitle' );

		$postImportHandler = new CentralAuthPostImportHandler(
			$fallbackHandler,
			$mockTemplateLookup,
			$this->createMock( RemoteApiActionExecutor::class )
		);

		$url = 'http://w.invalid/w/foo' . mt_rand();
		$status = $postImportHandler->execute(
			$this->createImportPlanMock( false, false, $url ),
			$this->getTestUser()->getUser()
		);
		$this->assertTrue( $status->isGood() );
	}

	public function testExecute_remoteEditSucceeds() {
		$url = 'http://w.invalid/w/foo' . mt_rand();
		$mockImportPlan = $this->createImportPlanMock( true, false, $url );
		$mockUser = $this->getTestUser()->getUser();

		$mockRemoteAction = $this->createMock( RemoteApiActionExecutor::class );
		$mockRemoteAction->expects( $this->once() )
			->method( 'executeEditAction' )
			->with(
				$this->anything(),
				$this->equalTo( $mockUser ),
				$this->equalTo( [
					'title' => 'TestTitleOriginal',
					'appendtext' => "\n{{TestNowCommons&#60;script&#62;|TestTitleEdited&#60;script&#62;}}",
					'summary' => '(fileimporter-cleanup-summary: http://TestUrl)',
				] )
			)
			// FIXME: not a realistic result, but we don't care yet.
			->willReturn( true );
		$mockTemplateLookup = $this->createMock( WikidataTemplateLookup::class );
		$mockTemplateLookup->expects( $this->once() )
			->method( 'fetchNowCommonsLocalTitle' )
			->willReturn( 'TestNowCommons<script>' );

		$postImportHandler = new CentralAuthPostImportHandler(
			$this->createMock( PostImportHandler::class ),
			$mockTemplateLookup,
			$mockRemoteAction
		);

		$status = $postImportHandler->execute( $mockImportPlan, $mockUser );
		$this->assertTrue( $status->isGood() );
	}

	public function testExecute_remoteEditFails() {
		$url = 'http://w.invalid/w/foo' . mt_rand();
		$mockImportPlan = $this->createImportPlanMock( true, false, $url );
		$mockUser = $this->getTestUser()->getUser();

		$fallbackHandler = $this->createMock( PostImportHandler::class );
		$fallbackHandler->expects( $this->once() )
			->method( 'execute' )
			->willReturn( StatusValue::newGood() );

		$mockRemoteAction = $this->createMock( RemoteApiActionExecutor::class );
		$mockRemoteAction->expects( $this->once() )
			->method( 'executeEditAction' );
		$mockTemplateLookup = $this->createMock( WikidataTemplateLookup::class );
		$mockTemplateLookup->expects( $this->once() )
			->method( 'fetchNowCommonsLocalTitle' );

		$postImportHandler = new CentralAuthPostImportHandler(
			$fallbackHandler,
			$mockTemplateLookup,
			$mockRemoteAction
		);

		$status = $postImportHandler->execute( $mockImportPlan, $mockUser );
		$this->assertTrue( $status->hasMessage( 'fileimporter-cleanup-failed' ) );
	}

	public function testExecute_remoteDeleteSucceeds() {
		$url = 'http://w.invalid/w/foo' . mt_rand();
		$mockImportPlan = $this->createImportPlanMock( false, true, $url );
		$mockUser = $this->getTestUser()->getUser();

		$mockRemoteAction = $this->createMock( RemoteApiActionExecutor::class );
		$mockRemoteAction->expects( $this->once() )
			->method( 'executeDeleteAction' )
			->with(
				$this->anything(),
				$this->equalTo( $mockUser ),
				$this->equalTo( [
					'title' => 'TestTitleOriginal',
					'reason' => '(fileimporter-delete-summary: http://TestUrl)',
				] )
			)
			// FIXME: not a realistic result, but we don't care yet.
			->willReturn( true );

		$postImportHandler = new CentralAuthPostImportHandler(
			$this->createMock( PostImportHandler::class ),
			$this->createMock( WikidataTemplateLookup::class ),
			$mockRemoteAction
		);

		$status = $postImportHandler->execute( $mockImportPlan, $mockUser );
		$this->assertTrue( $status->isGood() );
	}

	public function testExecute_remoteDeleteFails() {
		$host = 'w.' . mt_rand() . '.invalid';
		$url = 'http://w.invalid/w/foo' . mt_rand();
		$mockImportPlan = $this->createImportPlanMock( false, true, $url, $host );
		$mockUser = $this->getTestUser()->getUser();

		$mockRemoteAction = $this->createMock( RemoteApiActionExecutor::class );
		$mockRemoteAction->expects( $this->once() )
			->method( 'executeDeleteAction' );

		$postImportHandler = new CentralAuthPostImportHandler(
			$this->createMock( PostImportHandler::class ),
			$this->createMock( WikidataTemplateLookup::class ),
			$mockRemoteAction
		);

		$status = $postImportHandler->execute( $mockImportPlan, $mockUser );
		$this->assertTrue( $status->hasMessage( 'fileimporter-delete-failed' ) );
	}

	private function createImportPlanMock( $autoCleanup, $autoDelete, $url, $host = '' ) {
		$mockTitle = $this->createMock( Title::class );
		$mockTitle->method( 'getPrefixedText' )
			->willReturn( 'TestTitle' );
		$mockTitle->method( 'getFullURL' )
			->willReturn( 'http://TestUrl' );

		$mockOriginalTitle = $this->createMock( Title::class );
		$mockOriginalTitle->method( 'getPrefixedText' )
			->willReturn( 'TestTitleOriginal' );

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
		$mockImportPlan->method( 'getOriginalTitle' )
			->willReturn( $mockOriginalTitle );
		$mockImportPlan->method( 'getTitleText' )
			->willReturn( 'TestTitleEdited<script>' );

		return $mockImportPlan;
	}

}
