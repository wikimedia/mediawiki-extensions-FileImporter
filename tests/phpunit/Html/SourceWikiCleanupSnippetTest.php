<?php

namespace FileImporter\Tests\Html;

use FileImporter\Data\SourceUrl;
use FileImporter\Html\SourceWikiCleanupSnippet;
use FileImporter\Remote\MediaWiki\RemoteApiActionExecutor;
use FileImporter\Services\WikidataTemplateLookup;
use MediaWikiTestCase;
use User;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \FileImporter\Html\SourceWikiCleanupSnippet
 */
class SourceWikiCleanupSnippetTest extends MediaWikiTestCase {

	public function setUp() {
		parent::setUp();

		// Theme::setSingleton( new BlankTheme() );
	}

	public function testIsSourceEditAllowed_lookupSucceeds() {
		$this->setMwGlobals( 'wgFileImporterSourceWikiTemplating', true );
		$mockLookup = $this->createMock( WikidataTemplateLookup::class );
		$mockLookup
			->expects( $this->once() )
			->method( 'fetchNowCommonsLocalTitle' )
			->willReturn( 'template title' );
		$this->setService( 'FileImporterTemplateLookup', $mockLookup );
		$snippet = TestingAccessWrapper::newFromObject( new SourceWikiCleanupSnippet() );

		$this->assertTrue(
			$snippet->isSourceEditAllowed( $this->createMock( SourceUrl::class ) ) );
	}

	public function testIsSourceEditAllowed_lookupFails() {
		$this->setMwGlobals( 'wgFileImporterSourceWikiTemplating', true );
		$mockLookup = $this->createMock( WikidataTemplateLookup::class );
		$mockLookup
			->expects( $this->once() )
			->method( 'fetchNowCommonsLocalTitle' )
			->willReturn( null );
		$this->setService( 'FileImporterTemplateLookup', $mockLookup );
		$snippet = TestingAccessWrapper::newFromObject( new SourceWikiCleanupSnippet() );

		$this->assertFalse(
			$snippet->isSourceEditAllowed( $this->createMock( SourceUrl::class ) ) );
	}

	public function testIsSourceEditAllowed_configShortCircuits() {
		$this->setMwGlobals( 'wgFileImporterSourceWikiTemplating', false );
		$mockLookup = $this->createMock( WikidataTemplateLookup::class );
		$mockLookup
			->expects( $this->never() )
			->method( 'fetchNowCommonsLocalTitle' );
		$this->setService( 'FileImporterTemplateLookup', $mockLookup );
		$snippet = TestingAccessWrapper::newFromObject( new SourceWikiCleanupSnippet() );

		$this->assertFalse(
			$snippet->isSourceEditAllowed( $this->createMock( SourceUrl::class ) ) );
	}

	public function testIsSourceDeleteAllowed_success() {
		$this->setMwGlobals( 'wgFileImporterSourceWikiDeletion', true );
		$mockApi = $this->createMock( RemoteApiActionExecutor::class );
		$mockApi
			->expects( $this->once() )
			->method( 'executeUserRightsAction' )
			->willReturn( [ 'query' => [ 'userinfo' => [ 'rights' => [ 'delete', 'edit' ] ] ] ] );
		$this->setService( 'FileImporterMediaWikiRemoteApiActionExecutor', $mockApi );
		$snippet = TestingAccessWrapper::newFromObject( new SourceWikiCleanupSnippet() );

		$this->assertTrue(
			$snippet->isSourceDeleteAllowed(
				$this->createMock( SourceUrl::class ),
				new User() ) );
	}

	public function testIsSourceDeleteAllowed_notAllowed() {
		$this->setMwGlobals( 'wgFileImporterSourceWikiDeletion', true );
		$mockApi = $this->createMock( RemoteApiActionExecutor::class );
		$mockApi
			->expects( $this->once() )
			->method( 'executeUserRightsAction' )
			->willReturn( [ 'query' => [ 'userinfo' => [ 'rights' => [ 'edit' ] ] ] ] );
		$this->setService( 'FileImporterMediaWikiRemoteApiActionExecutor', $mockApi );
		$snippet = TestingAccessWrapper::newFromObject( new SourceWikiCleanupSnippet() );

		$this->assertFalse(
			$snippet->isSourceDeleteAllowed(
				$this->createMock( SourceUrl::class ),
				new User() ) );
	}

	public function testIsSourceDeleteAllowed_apiFailure() {
		$this->setMwGlobals( 'wgFileImporterSourceWikiDeletion', true );
		$mockApi = $this->createMock( RemoteApiActionExecutor::class );
		$mockApi
			->expects( $this->once() )
			->method( 'executeUserRightsAction' )
			->willReturn( null );
		$this->setService( 'FileImporterMediaWikiRemoteApiActionExecutor', $mockApi );
		$snippet = TestingAccessWrapper::newFromObject( new SourceWikiCleanupSnippet() );

		$this->assertFalse(
			$snippet->isSourceDeleteAllowed(
				$this->createMock( SourceUrl::class ),
				new User() ) );
	}

	public function testIsSourceDeleteAllowed_configShortCircuits() {
		$this->setMwGlobals( 'wgFileImporterSourceWikiDeletion', false );
		$mockApi = $this->createMock( RemoteApiActionExecutor::class );
		$mockApi
			->expects( $this->never() )
			->method( 'executeUserRightsAction' );
		$this->setService( 'FileImporterMediaWikiRemoteApiActionExecutor', $mockApi );
		$snippet = TestingAccessWrapper::newFromObject( new SourceWikiCleanupSnippet() );

		$this->assertFalse(
			$snippet->isSourceDeleteAllowed(
				$this->createMock( SourceUrl::class ),
				new User() ) );
	}

}
