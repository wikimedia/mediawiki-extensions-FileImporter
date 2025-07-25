<?php

namespace FileImporter\Tests\Remote\MediaWiki;

use FileImporter\Data\ImportDetails;
use FileImporter\Data\ImportPlan;
use FileImporter\Data\SourceUrl;
use FileImporter\Interfaces\PostImportHandler;
use FileImporter\Remote\MediaWiki\RemoteApiActionExecutor;
use FileImporter\Remote\MediaWiki\RemoteSourceFileEditDeleteAction;
use FileImporter\Services\WikidataTemplateLookup;
use MediaWiki\MainConfigNames;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use MediaWiki\Utils\UrlUtils;
use MediaWikiIntegrationTestCase;
use StatusValue;

/**
 * @covers \FileImporter\Remote\MediaWiki\RemoteSourceFileEditDeleteAction
 *
 * @license GPL-2.0-or-later
 */
class RemoteSourceFileEditDeleteActionTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		$this->overrideConfigValue( MainConfigNames::LanguageCode, 'qqx' );
		parent::setUp();
	}

	public function testExecute_noCleanupRequested() {
		$fallbackHandler = $this->createMock( PostImportHandler::class );
		$fallbackHandler->expects( $this->once() )
			->method( 'execute' )
			->willReturn( StatusValue::newGood() );

		$postImportHandler = new RemoteSourceFileEditDeleteAction(
			$fallbackHandler,
			$this->createNoOpMock( WikidataTemplateLookup::class ),
			$this->createNoOpMock( RemoteApiActionExecutor::class ),
			new UrlUtils()
		);

		$url = '//w.invalid/w/foo' . mt_rand();
		$status = $postImportHandler->execute(
			$this->createImportPlanMock( false, false, $url ),
			$this->createNoOpMock( User::class )
		);
		$this->assertTrue( $status->isGood() );
	}

	public function testExecute_remoteEditSucceeds() {
		$url = '//w.invalid/w/foo' . mt_rand();
		$mockImportPlan = $this->createImportPlanMock( true, false, $url );
		$mockUser = $this->createNoOpMock( User::class );

		$mockRemoteAction = $this->createMock( RemoteApiActionExecutor::class );
		$mockRemoteAction->expects( $this->once() )
			->method( 'executeEditAction' )
			->with(
				$this->isInstanceOf( SourceUrl::class ),
				$mockUser,
				'File:TestTitleOriginal',
				[
					'appendtext' => "\n{{TestNowCommons&#60;script&#62;|TestTitleEdited&#60;script&#62;}}",
				],
				'(fileimporter-cleanup-summary: http://TestUrl/File:Берлін_2011-2.JPG)'
			)
			->willReturn( StatusValue::newGood() );
		$mockTemplateLookup = $this->createMock( WikidataTemplateLookup::class );
		$mockTemplateLookup->expects( $this->once() )
			->method( 'fetchNowCommonsLocalTitle' )
			->willReturn( 'TestNowCommons<script>' );

		$postImportHandler = new RemoteSourceFileEditDeleteAction(
			$this->createNoOpMock( PostImportHandler::class ),
			$mockTemplateLookup,
			$mockRemoteAction,
			new UrlUtils()
		);

		$status = $postImportHandler->execute( $mockImportPlan, $mockUser );
		$this->assertTrue( $status->isGood() );
	}

	public function testExecute_remoteEditFails() {
		$url = '//w.invalid/w/foo' . mt_rand();
		$mockImportPlan = $this->createImportPlanMock( true, false, $url );
		$mockUser = $this->createNoOpMock( User::class );

		$fallbackHandler = $this->createMock( PostImportHandler::class );
		$fallbackHandler->expects( $this->once() )
			->method( 'execute' )
			->willReturn( StatusValue::newGood() );

		$mockRemoteAction = $this->createMock( RemoteApiActionExecutor::class );
		$mockRemoteAction->expects( $this->once() )
			->method( 'executeEditAction' );
		$mockTemplateLookup = $this->createMock( WikidataTemplateLookup::class );
		$mockTemplateLookup->expects( $this->once() )
			->method( 'fetchNowCommonsLocalTitle' )
			->willReturn( 'NowCommons' );

		$postImportHandler = new RemoteSourceFileEditDeleteAction(
			$fallbackHandler,
			$mockTemplateLookup,
			$mockRemoteAction,
			new UrlUtils()
		);

		$status = $postImportHandler->execute( $mockImportPlan, $mockUser );
		$this->assertTrue( $status->hasMessage( 'fileimporter-cleanup-failed' ) );
	}

	public function testExecute_remoteDeleteSucceeds() {
		$url = '//w.invalid/w/foo' . mt_rand();
		$mockImportPlan = $this->createImportPlanMock( false, true, $url );
		$mockUser = $this->createNoOpMock( User::class );

		$mockRemoteAction = $this->createMock( RemoteApiActionExecutor::class );
		$mockRemoteAction->expects( $this->once() )
			->method( 'executeDeleteAction' )
			->with(
				$this->isInstanceOf( SourceUrl::class ),
				$mockUser,
				'File:TestTitleOriginal',
				'(fileimporter-delete-summary: http://TestUrl/File:Берлін_2011-2.JPG)'
			)
			->willReturn( StatusValue::newGood() );

		$postImportHandler = new RemoteSourceFileEditDeleteAction(
			$this->createNoOpMock( PostImportHandler::class ),
			$this->createNoOpMock( WikidataTemplateLookup::class ),
			$mockRemoteAction,
			new UrlUtils()
		);

		$status = $postImportHandler->execute( $mockImportPlan, $mockUser );
		$this->assertTrue( $status->isGood() );
	}

	public function testExecute_remoteDeleteFails() {
		$host = 'w.' . mt_rand() . '.invalid';
		$url = '//w.invalid/w/foo' . mt_rand();
		$mockImportPlan = $this->createImportPlanMock( false, true, $url, $host );
		$mockUser = $this->createNoOpMock( User::class );

		$mockRemoteAction = $this->createMock( RemoteApiActionExecutor::class );
		$mockRemoteAction->expects( $this->once() )
			->method( 'executeDeleteAction' );

		$postImportHandler = new RemoteSourceFileEditDeleteAction(
			$this->createNoOpMock( PostImportHandler::class ),
			$this->createNoOpMock( WikidataTemplateLookup::class ),
			$mockRemoteAction,
			new UrlUtils()
		);

		$status = $postImportHandler->execute( $mockImportPlan, $mockUser );
		$this->assertTrue( $status->hasMessage( 'fileimporter-delete-failed' ) );
	}

	private function createImportPlanMock(
		bool $autoCleanup,
		bool $autoDelete,
		string $url,
		string $host = ''
	): ImportPlan {
		$mockTitle = $this->createMock( Title::class );
		$mockTitle->method( 'getPrefixedText' )
			->willReturn( 'TestTitle' );
		$mockTitle->method( 'getText' )
			->willReturn( 'TestTitleEdited<script>' );
		$mockTitle->method( 'getFullURL' )
			->willReturn( 'http://TestUrl/File:%D0%91%D0%B5%D1%80%D0%BB%D1%96%D0%BD_2011-2.JPG' );

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
			->willReturn( Title::makeTitle( NS_FILE, 'TestTitleOriginal' ) );

		return $mockImportPlan;
	}

}
