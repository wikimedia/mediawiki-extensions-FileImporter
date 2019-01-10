<?php

namespace FileImporter\Html\Test;

use DerivativeContext;
use FauxRequest;
use FileImporter\Data\ImportDetails;
use FileImporter\Data\ImportPlan;
use FileImporter\Data\ImportRequest;
use FileImporter\Data\TextRevision;
use FileImporter\Data\TextRevisions;
use FileImporter\Html\FileInfoDiffPage;
use IContextSource;
use Language;
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
 * @license GPL-2.0-or-later
 * @author Christoph Jauera <christoph.jauera@wikimedia.de>
 */
class FileInfoDiffPageTest extends \MediaWikiTestCase {

	public function setUp() {
		parent::setUp();
		$this->setUserLang( 'qqx' );
		$this->setMwGlobals( [ 'wgFileImporterTextForPostImportRevision' => '' ] );
		Theme::setSingleton( new BlankTheme() );
	}

	public function tearDown() {
		Theme::setSingleton( null );
		parent::tearDown();
	}

	/**
	 * @return SpecialPage
	 */
	private function getMockSpecialPage() {
		$title = Title::newFromText( __METHOD__ );

		$mock = $this->createMock( SpecialPage::class );
		$mock->method( 'getPageTitle' )
			->willReturn( $title );
		$mock->method( 'getContext' )
			->willReturn( $this->getTestContext( $title ) );
		$mock->method( 'getRequest' )
			->willReturn( new FauxRequest( [ 'importDetailsHash' => 'FAKEHASH' ] ) );
		$mock->method( 'getLanguage' )
			->willReturn( $this->createMock( Language::class ) );
		$mock->method( 'msg' )
			->willReturn( new \RawMessage( '' ) );
		return $mock;
	}

	/**
	 * @param Title $title
	 * @return IContextSource
	 */
	private function getTestContext( Title $title ) {
		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setTitle( $title );
		return $context;
	}

	/**
	 * @param string $originalInput
	 * @return ImportDetails
	 */
	private function getMockImportDetails( $originalInput ) {
		$mock = $this->createMock( ImportDetails::class );
		$mock->method( 'getTextRevisions' )
			->willReturn( $this->getMockTextRevisions( $originalInput ) );
		return $mock;
	}

	/**
	 * @param string $originalInput
	 * @return TextRevisions
	 */
	private function getMockTextRevisions( $originalInput ) {
		$mock = $this->createMock( TextRevisions::class );
		$mock->method( 'getLatest' )
			->willReturn( $this->getMockTextRevision( $originalInput ) );
		return $mock;
	}

	/**
	 * @param string $originalInput
	 * @return TextRevision
	 */
	private function getMockTextRevision( $originalInput ) {
		$mock = $this->createMock( TextRevision::class );
		$mock->method( 'getField' )
			->willReturn( $originalInput );
		return $mock;
	}

	public function provideTestTextDisplayedInInputBox() {
		return [
			[ 'This is old text.', "This is new text.", "This is new text." ],
			[ 'This is old text.', "This is old text.", "(diff-empty)" ],
		];
	}

	/**
	 * @dataProvider provideTestTextDisplayedInInputBox
	 */
	public function testTextDisplayedInInputBox( $originalInput, $userInput, $expected ) {
		$importPlan = new ImportPlan(
			new ImportRequest( 'http://goog', 'Foo', $userInput ),
			$this->getMockImportDetails( $originalInput ),
			''
		);
		$diffPage = new FileInfoDiffPage( $this->getMockSpecialPage() );

		assertThat(
			$diffPage->getHtml( $importPlan ),
			is( htmlPiece( havingChild(
				both( withTagName( 'div' ) )
					->andAlso( havingTextContents( $expected ) )
			) ) )
		);
		// Avoid marking as a risky test
		$this->addToAssertionCount( 1 );
	}

}
