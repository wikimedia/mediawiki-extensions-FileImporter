<?php

namespace FileImporter\Tests\Html;

use DerivativeContext;
use FileImporter\Data\ImportDetails;
use FileImporter\Data\ImportPlan;
use FileImporter\Data\ImportRequest;
use FileImporter\Data\TextRevision;
use FileImporter\Data\TextRevisions;
use FileImporter\Html\FileInfoDiffPage;
use HashConfig;
use IContextSource;
use Language;
use MediaWiki\Request\FauxRequest;
use MessageLocalizer;
use OOUI\BlankTheme;
use OOUI\Theme;
use RequestContext;
use SpecialPage;
use Title;

/**
 * @covers \FileImporter\Html\FileInfoDiffPage
 * @covers \FileImporter\Html\ImportIdentityFormSnippet
 * @covers \FileImporter\Html\SpecialPageHtmlFragment
 *
 * @group Database
 * @license GPL-2.0-or-later
 * @author Christoph Jauera <christoph.jauera@wikimedia.de>
 */
class FileInfoDiffPageTest extends \MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->setUserLang( 'qqx' );
		$this->setMwGlobals( [ 'wgFileImporterTextForPostImportRevision' => '' ] );
		Theme::setSingleton( new BlankTheme() );
	}

	protected function tearDown(): void {
		Theme::setSingleton();
		parent::tearDown();
	}

	private function getMockSpecialPage(): SpecialPage {
		$title = Title::makeTitle( NS_MAIN, __METHOD__ );

		$mock = $this->createMock( SpecialPage::class );
		$mock->method( 'getPageTitle' )
			->willReturn( $title );
		$mock->method( 'getContext' )
			->willReturn( $this->getTestContext( $title ) );
		$mock->method( 'getRequest' )
			->willReturn( new FauxRequest( [ 'importDetailsHash' => 'FAKEHASH' ] ) );
		$mock->method( 'getLanguage' )
			->willReturn( $this->createMock( Language::class ) );
		return $mock;
	}

	private function getTestContext( Title $title ): IContextSource {
		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setTitle( $title );
		return $context;
	}

	/**
	 * @param string $originalInput
	 * @return ImportDetails
	 */
	private function getMockImportDetails( string $originalInput ): ImportDetails {
		$mock = $this->createMock( ImportDetails::class );
		$mock->method( 'getTextRevisions' )
			->willReturn( $this->getMockTextRevisions( $originalInput ) );
		return $mock;
	}

	/**
	 * @param string $originalInput
	 * @return TextRevisions
	 */
	private function getMockTextRevisions( string $originalInput ): TextRevisions {
		$mock = $this->createMock( TextRevisions::class );
		$mock->method( 'getLatest' )
			->willReturn( $this->getMockTextRevision( $originalInput ) );
		return $mock;
	}

	/**
	 * @param string $originalInput
	 * @return TextRevision
	 */
	private function getMockTextRevision( string $originalInput ): TextRevision {
		$mock = $this->createMock( TextRevision::class );
		$mock->method( 'getContent' )
			->willReturn( $originalInput );
		return $mock;
	}

	public static function provideTestTextDisplayedInInputBox() {
		return [
			[
				'This is old text.',
				'This is new text.',
				'<div>This is <ins class="diffchange diffchange-inline">new'
			],
			[
				'This is old text.',
				'This is old text.',
				'<div class="mw-diff-empty">(diff-empty)</div>'
			],
		];
	}

	/**
	 * @dataProvider provideTestTextDisplayedInInputBox
	 */
	public function testTextDisplayedInInputBox( string $originalInput, string $userInput, string $expected ) {
		$importPlan = new ImportPlan(
			new ImportRequest( '//w.invalid', 'Foo', $userInput ),
			$this->getMockImportDetails( $originalInput ),
			new HashConfig(),
			$this->createMock( MessageLocalizer::class ),
			''
		);

		$contentHandler = $this->getServiceContainer()->getContentHandlerFactory()
			->getContentHandler( CONTENT_MODEL_WIKITEXT );

		$diffPage = new FileInfoDiffPage( $this->getMockSpecialPage() );

		$this->assertStringContainsString(
			$expected,
			$diffPage->getHtml( $importPlan, $contentHandler )
		);
	}

}
