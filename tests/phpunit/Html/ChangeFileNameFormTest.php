<?php

namespace FileImporter\Html\Test;

use FauxRequest;
use FileImporter\Data\ImportDetails;
use FileImporter\Data\ImportPlan;
use FileImporter\Data\ImportRequest;
use FileImporter\Data\TextRevision;
use FileImporter\Data\TextRevisions;
use FileImporter\Html\ChangeFileNameForm;
use OOUI\Theme;
use OOUI\WikimediaUITheme;
use SpecialPage;
use Title;

/**
 * @covers \FileImporter\Html\ChangeFileNameForm
 *
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class ChangeFileNameFormTest extends \PHPUnit\Framework\TestCase {

	public function setUp() {
		parent::setUp();
		Theme::setSingleton( new WikimediaUITheme() );
	}

	/**
	 * @return SpecialPage
	 */
	private function getMockSpecialPage() {
		$mock = $this->getMock( SpecialPage::class, [], [], '', false );
		$mock->method( 'getPageTitle' )
			->willReturn( Title::newFromText( 'SomeTitle' ) );
		$mock->method( 'getRequest' )
			->willReturn( new FauxRequest( [ 'importDetailsHash' => 'FAKEHASH' ] ) );
		return $mock;
	}

	/**
	 * @return \PHPUnit_Framework_MockObject_MockObject|ImportDetails
	 */
	private function getMockImportDetails() {
		$mock = $this->getMock( ImportDetails::class, [], [], '', false );
		$mock->method( 'getTextRevisions' )
			->willReturn( $this->getMockTextRevisions() );
		return $mock;
	}

	/**
	 * @return \PHPUnit_Framework_MockObject_MockObject|TextRevisions
	 */
	private function getMockTextRevisions() {
		$mock = $this->getMock( TextRevisions::class, [], [], '', false );
		$mock->method( 'getLatest' )
			->willReturn( $this->getMockTextRevision() );
		return $mock;
	}

	/**
	 * @return \PHPUnit_Framework_MockObject_MockObject|TextRevision
	 */
	private function getMockTextRevision() {
		$mock = $this->getMock( TextRevision::class, [], [], '', false );
		$mock->method( 'getField' )
			->willReturn( '' );
		return $mock;
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
		$this->addToAssertionCount( 1 );
	}

}
