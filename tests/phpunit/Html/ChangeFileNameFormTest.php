<?php

namespace FileImporter\Html\Test;

use FauxRequest;
use FileImporter\Data\ImportDetails;
use FileImporter\Data\ImportPlan;
use FileImporter\Data\ImportRequest;
use FileImporter\Html\ChangeFileNameForm;
use OOUI\MediaWikiTheme;
use OOUI\Theme;
use PHPUnit_Framework_TestCase;
use SpecialPage;
use Title;

class ChangeFileNameFormTest extends PHPUnit_Framework_TestCase {

	public function setUp() {
		parent::setUp();
		Theme::setSingleton( new MediaWikiTheme );
	}

	private function getMockSpecialPage() {
		$mock = $this->getMock( SpecialPage::class, [], [], '', false );
		$mock->expects( $this->any() )
			->method( 'getPageTitle' )
			->will( $this->returnValue( Title::newFromText( 'SomeTitle' ) ) );
		$mock->expects( $this->any() )
			->method( 'getRequest' )
			->will( $this->returnValue( new FauxRequest( [ 'importDetailsHash' => 'FAKEHASH' ] ) ) );
		return $mock;
	}

	/**
	 * @return \PHPUnit_Framework_MockObject_MockObject|ImportDetails
	 */
	private function getMockImportDetails() {
		return $this->getMock( ImportDetails::class, [], [], '', false );
	}

	public function provideTestTextDisplayedInInputBox() {
		return [
			[ 'Foo', 'Foo' ],
			[ 'Foooo/Barr', 'Foooo/Barr' ],
		];
	}

	/**
	 * @dataProvider provideTestTextDisplayedInInputBox
	 * @param string $userInputName
	 * @param string $expectedInputText
	 */
	public function testTextDisplayedInInputBox( $userInputName, $expectedInputText ) {
		$importPlan = new ImportPlan(
			new ImportRequest( 'http://goog', $userInputName, '' ),
			$this->getMockImportDetails()
		);
		$form = new ChangeFileNameForm( $this->getMockSpecialPage(), $importPlan );

		assertThat(
			$form->getHtml(),
			is( htmlPiece( havingChild(
				both( withTagName( 'input' ) )
					->andAlso( withAttribute( 'name' )->havingValue( 'intendedFileName' ) )
					->andAlso( withAttribute( 'value' )->havingValue( $expectedInputText ) )
			) ) )
		);
		// Avoid marking as a risky test
		$this->assertTrue( true );
	}

}
