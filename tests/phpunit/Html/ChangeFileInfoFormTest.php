<?php

namespace FileImporter\Html\Test;

use FauxRequest;
use FileImporter\Data\ImportDetails;
use FileImporter\Data\ImportPlan;
use FileImporter\Data\ImportRequest;
use FileImporter\Data\TextRevision;
use FileImporter\Data\TextRevisions;
use FileImporter\Html\ChangeFileInfoForm;
use IContextSource;
use Language;
use OOUI\BlankTheme;
use OOUI\Theme;
use OutputPage;
use PHPUnit4And6Compat;
use SpecialPage;
use Title;
use User;

/**
 * @covers \FileImporter\Html\ChangeFileInfoForm
 * @covers \FileImporter\Html\ImportIdentityFormSnippet
 * @covers \FileImporter\Html\WikiTextEditor
 *
 * @license GPL-2.0-or-later
 * @author Christoph Jauera <christoph.jauera@wikimedia.de>
 */
class ChangeFileInfoFormTest extends \PHPUnit\Framework\TestCase {
	use PHPUnit4And6Compat;

	public function setUp() {
		parent::setUp();
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
		$mock = $this->createMock( SpecialPage::class );
		$mock->method( 'getPageTitle' )
			->willReturn( Title::newFromText( 'SomeTitle' ) );
		$mock->method( 'getContext' )
			->willReturn( $this->createMock( IContextSource::class ) );
		$mock->method( 'getRequest' )
			->willReturn( new FauxRequest( [ 'importDetailsHash' => 'FAKEHASH' ] ) );
		$mock->method( 'getOutput' )
			->willReturn( $this->createMock( OutputPage::class ) );
		$mock->method( 'getUser' )
			->willReturn( $this->createMock( User::class ) );
		$mock->method( 'getLanguage' )
			->willReturn( $this->createMock( Language::class ) );
		return $mock;
	}

	/**
	 * @return ImportDetails
	 */
	private function getMockImportDetails() {
		$mock = $this->createMock( ImportDetails::class );
		$mock->method( 'getTextRevisions' )
			->willReturn( $this->getMockTextRevisions() );
		return $mock;
	}

	/**
	 * @return TextRevisions
	 */
	private function getMockTextRevisions() {
		$mock = $this->createMock( TextRevisions::class );
		$mock->method( 'getLatest' )
			->willReturn( $this->getMockTextRevision() );
		return $mock;
	}

	/**
	 * @return TextRevision
	 */
	private function getMockTextRevision() {
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
			new ImportRequest( 'http://goog', 'Foo', $userInput ),
			$this->getMockImportDetails()
		);
		$form = new ChangeFileInfoForm( $this->getMockSpecialPage(), $importPlan );

		assertThat(
			$form->getHtml(),
			is( htmlPiece( havingChild(
				both( withTagName( 'textarea' ) )
					->andAlso( withAttribute( 'name' )->havingValue( 'intendedWikiText' ) )
					->andAlso( havingTextContents( $expectedInputText ) )
			) ) )
		);
		// Avoid marking as a risky test
		$this->addToAssertionCount( 1 );
	}

}
