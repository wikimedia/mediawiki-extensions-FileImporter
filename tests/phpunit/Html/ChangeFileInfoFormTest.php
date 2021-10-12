<?php

namespace FileImporter\Tests\Html;

use Config;
use FauxRequest;
use FileImporter\Data\ImportDetails;
use FileImporter\Data\ImportPlan;
use FileImporter\Data\ImportRequest;
use FileImporter\Data\TextRevision;
use FileImporter\Data\TextRevisions;
use FileImporter\Html\ChangeFileInfoForm;
use HamcrestPHPUnitIntegration;
use Language;
use MessageLocalizer;
use OOUI\BlankTheme;
use OOUI\Theme;
use OutputPage;
use RequestContext;
use SpecialPage;
use Title;

/**
 * @covers \FileImporter\Html\ChangeFileInfoForm
 * @covers \FileImporter\Html\ImportIdentityFormSnippet
 * @covers \FileImporter\Html\SpecialPageHtmlFragment
 * @covers \FileImporter\Html\WikitextEditor
 *
 * @license GPL-2.0-or-later
 * @author Christoph Jauera <christoph.jauera@wikimedia.de>
 */
class ChangeFileInfoFormTest extends \MediaWikiIntegrationTestCase {
	use HamcrestPHPUnitIntegration;

	protected function setUp(): void {
		parent::setUp();

		$this->setMwGlobals( 'wgHooks', [] );
		Theme::setSingleton( new BlankTheme() );
	}

	protected function tearDown(): void {
		Theme::setSingleton( null );

		parent::tearDown();
	}

	private function getMockSpecialPage(): SpecialPage {
		$user = $this->getTestUser()->getUser();
		$request = new FauxRequest( [ 'importDetailsHash' => 'FAKEHASH' ] );

		$context = $this->createMock( RequestContext::class );
		$context->method( 'getRequest' )
			->willReturn( $request );

		$mock = $this->createMock( SpecialPage::class );
		$mock->method( 'getPageTitle' )
			->willReturn( Title::newFromText( __METHOD__ ) );
		$mock->method( 'getContext' )
			->willReturn( $context );
		$mock->method( 'getOutput' )
			->willReturn( $this->createMock( OutputPage::class ) );
		$mock->method( 'getUser' )
			->willReturn( $user );
		$mock->method( 'getLanguage' )
			->willReturn( $this->createMock( Language::class ) );
		$mock->method( 'msg' )
			->willReturn( new \RawMessage( '' ) );
		return $mock;
	}

	private function getMockImportDetails(): ImportDetails {
		$mock = $this->createMock( ImportDetails::class );
		$mock->method( 'getTextRevisions' )
			->willReturn( $this->getMockTextRevisions() );
		return $mock;
	}

	private function getMockTextRevisions(): TextRevisions {
		$mock = $this->createMock( TextRevisions::class );
		$mock->method( 'getLatest' )
			->willReturn( $this->getMockTextRevision() );
		return $mock;
	}

	private function getMockTextRevision(): TextRevision {
		$mock = $this->createMock( TextRevision::class );
		$mock->method( 'getField' )
			->willReturn( '' );
		return $mock;
	}

	public function provideTestTextDisplayedInInputBox() {
		return [
			[ 'Some Input Text', "Some Input Text\n" ],
			[ 'Some Input Text ', "Some Input Text\n" ],
		];
	}

	/**
	 * @dataProvider provideTestTextDisplayedInInputBox
	 * @param string $userInput
	 * @param string $expectedInputText
	 */
	public function testTextDisplayedInInputBox( $userInput, $expectedInputText ) {
		$importPlan = new ImportPlan(
			new ImportRequest( '//w.invalid', 'Foo', $userInput ),
			$this->getMockImportDetails(),
			$this->createMock( Config::class ),
			$this->createMock( MessageLocalizer::class ),
			''
		);
		$form = new ChangeFileInfoForm( $this->getMockSpecialPage() );

		$this->assertThatHamcrest(
			$form->getHtml( $importPlan ),
			is( htmlPiece( havingChild(
				both( withTagName( 'form' ) )
					->andAlso( havingChild(
						both( withTagName( 'textarea' ) )
							->andAlso( withAttribute( 'name' )->havingValue( 'intendedWikitext' ) )
							->andAlso( havingTextContents( $expectedInputText ) )
					) )
					->andAlso( havingChild(
						both( withTagName( 'input' ) )
							->andAlso( withAttribute( 'name' )->havingValue( 'actionStats' ) )
							->andAlso( withAttribute( 'value' )->havingValue( '[]' ) )
					) )
					->andAlso( havingChild(
						both( withTagName( 'input' ) )
							->andAlso( withAttribute( 'name' )->havingValue( 'validationWarnings' ) )
							->andAlso( withAttribute( 'value' )->havingValue( '[]' ) )
					) )
			) ) )
		);
	}

}
