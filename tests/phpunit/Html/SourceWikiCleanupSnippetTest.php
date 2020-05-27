<?php

namespace FileImporter\Tests\Html;

use FileImporter\Data\ImportDetails;
use FileImporter\Data\ImportPlan;
use FileImporter\Data\ImportRequest;
use FileImporter\Data\SourceUrl;
use FileImporter\Html\SourceWikiCleanupSnippet;
use FileImporter\Remote\MediaWiki\RemoteApiActionExecutor;
use FileImporter\Services\WikidataTemplateLookup;
use MediaWiki\Linker\LinkTarget;
use MediaWikiTestCase;
use OOUI\BlankTheme;
use OOUI\Theme;
use User;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \FileImporter\Html\SourceWikiCleanupSnippet
 *
 * @license GPL-2.0-or-later
 */
class SourceWikiCleanupSnippetTest extends MediaWikiTestCase {

	public function setUp() : void {
		parent::setUp();

		Theme::setSingleton( new BlankTheme() );
	}

	public function provideNoSnippetSetups() {
		yield [ false, false, false, false ];
		yield [ false, false, true, true ];
		yield [ true, true, false, false ];
	}

	/**
	 * @dataProvider provideNoSnippetSetups
	 */
	public function testGetHtml_noCleanupSnippet(
		$templateKnown,
		$userCanDelete,
		$editEnabled,
		$deleteEnabled
	) {
		$this->setupServicesAndGlobals( $templateKnown, $userCanDelete );

		$snippet = new SourceWikiCleanupSnippet( $editEnabled, $deleteEnabled );
		$this->assertSame(
			'',
			$snippet->getHtml(
				$this->createImportPlan(),
				$this->createMock( User::class )
			)
		);
	}

	public function testGetHtml_editPreselected() {
		$this->setupServicesAndGlobals( true, false );

		$snippet = new SourceWikiCleanupSnippet();
		$html = $snippet->getHtml(
			$this->createImportPlan(),
			$this->createMock( User::class )
		);

		$this->assertStringContainsString(
			" name='automateSourceWikiCleanup' value='1' checked='checked'",
			$html
		);
	}

	public function testIsSourceEditAllowed_lookupSucceeds() {
		$this->setupServicesAndGlobals( true, false );
		/** @var SourceWikiCleanupSnippet $snippet */
		$snippet = TestingAccessWrapper::newFromObject( new SourceWikiCleanupSnippet() );

		$this->assertTrue( $snippet->isSourceEditAllowed(
			$this->createMock( SourceUrl::class ),
			$this->createMock( User::class ),
			''
		) );
	}

	public function testIsSourceEditAllowed_lookupFails() {
		$this->setupServicesAndGlobals( false, false );
		/** @var SourceWikiCleanupSnippet $snippet */
		$snippet = TestingAccessWrapper::newFromObject( new SourceWikiCleanupSnippet() );

		$this->assertFalse( $snippet->isSourceEditAllowed(
			$this->createMock( SourceUrl::class ),
			$this->createMock( User::class ),
			''
		) );
	}

	public function testIsSourceEditAllowed_configShortCircuits() {
		$mockLookup = $this->createMock( WikidataTemplateLookup::class );
		$mockLookup
			->expects( $this->never() )
			->method( 'fetchNowCommonsLocalTitle' );
		$this->setService( 'FileImporterTemplateLookup', $mockLookup );
		/** @var SourceWikiCleanupSnippet $snippet */
		$snippet = TestingAccessWrapper::newFromObject( new SourceWikiCleanupSnippet( false ) );

		$this->assertFalse( $snippet->isSourceEditAllowed(
			$this->createMock( SourceUrl::class ),
			$this->createMock( User::class ),
			''
		) );
	}

	public function testIsSourceDeleteAllowed_success() {
		$this->setupServicesAndGlobals( false, true );
		/** @var SourceWikiCleanupSnippet $snippet */
		$snippet = TestingAccessWrapper::newFromObject( new SourceWikiCleanupSnippet() );

		$this->assertTrue(
			$snippet->isSourceDeleteAllowed(
				$this->createMock( SourceUrl::class ),
				new User() ) );
	}

	public function testIsSourceDeleteAllowed_notAllowed() {
		$mockApi = $this->createMock( RemoteApiActionExecutor::class );
		$mockApi
			->expects( $this->once() )
			->method( 'executeUserRightsQuery' )
			->willReturn( [ 'query' => [ 'userinfo' => [ 'rights' => [ 'edit' ] ] ] ] );
		$this->setService( 'FileImporterMediaWikiRemoteApiActionExecutor', $mockApi );
		/** @var SourceWikiCleanupSnippet $snippet */
		$snippet = TestingAccessWrapper::newFromObject( new SourceWikiCleanupSnippet() );

		$this->assertFalse(
			$snippet->isSourceDeleteAllowed(
				$this->createMock( SourceUrl::class ),
				new User() ) );
	}

	public function testIsSourceDeleteAllowed_apiFailure() {
		$this->setupServicesAndGlobals( false, false );
		$snippet = TestingAccessWrapper::newFromObject( new SourceWikiCleanupSnippet() );

		$this->assertFalse(
			$snippet->isSourceDeleteAllowed(
				$this->createMock( SourceUrl::class ),
				new User() ) );
	}

	public function testIsSourceDeleteAllowed_configShortCircuits() {
		$mockApi = $this->createMock( RemoteApiActionExecutor::class );
		$mockApi
			->expects( $this->never() )
			->method( 'executeUserRightsQuery' );
		$this->setService( 'FileImporterMediaWikiRemoteApiActionExecutor', $mockApi );
		/** @var SourceWikiCleanupSnippet $snippet */
		$snippet = TestingAccessWrapper::newFromObject( new SourceWikiCleanupSnippet( true, false ) );

		$this->assertFalse(
			$snippet->isSourceDeleteAllowed(
				$this->createMock( SourceUrl::class ),
				new User() ) );
	}

	public function testIsFreshImport_true() {
		$request = new ImportRequest( '//w.invalid', null, null, null, '' );

		$snippet = TestingAccessWrapper::newFromObject( new SourceWikiCleanupSnippet() );
		$this->assertTrue( $snippet->isFreshImport( $request ) );
	}

	public function testIsFreshImport_false() {
		$request = new ImportRequest( '//w.invalid', null, null, null, 'a' );

		/** @var SourceWikiCleanupSnippet $snippet */
		$snippet = TestingAccessWrapper::newFromObject( new SourceWikiCleanupSnippet() );
		$this->assertFalse( $snippet->isFreshImport( $request ) );
	}

	private function createImportPlan() {
		$importDetails = $this->createMock( ImportDetails::class );
		$importDetails->method( 'getSourceLinkTarget' )
			->willReturn( $this->createMock( LinkTarget::class ) );

		return new ImportPlan(
			new ImportRequest( '//w.invalid' ),
			$importDetails,
			''
		);
	}

	private function setupServicesAndGlobals( $templateKnown, $userCanDelete ) {
		$templateResult = $templateKnown ? 'TestNowCommons' : null;
		$rightsApiResult = $userCanDelete ?
			[ 'query' => [ 'userinfo' => [ 'rights' => [ 'delete' ] ] ] ] : null;

		$mockTemplateLookup = $this->createMock( WikidataTemplateLookup::class );
		$mockTemplateLookup
			->method( 'fetchNowCommonsLocalTitle' )
			->willReturn( $templateResult );
		$this->setService( 'FileImporterTemplateLookup', $mockTemplateLookup );
		$mockApiExecutor = $this->createMock( RemoteApiActionExecutor::class );
		$mockApiExecutor->method( 'executeTestEditActionQuery' )
			->willReturn( [ 'query' => [ 'pages' => [ [ 'actions' => [ 'edit' => '' ] ] ] ] ] );
		$mockApiExecutor
			->method( 'executeUserRightsQuery' )
			->willReturn( $rightsApiResult );
		$this->setService( 'FileImporterMediaWikiRemoteApiActionExecutor', $mockApiExecutor );
	}

}
