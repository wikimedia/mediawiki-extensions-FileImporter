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
use PHPUnit4And6Compat;
use SpecialPage;
use Title;

/**
 * @covers \FileImporter\Html\ChangeFileNameForm
 *
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class ChangeFileNameFormTest extends \PHPUnit\Framework\TestCase {
	use PHPUnit4And6Compat;

	public function setUp() {
		parent::setUp();
		Theme::setSingleton( new WikimediaUITheme() );
	}

	/**
	 * @return SpecialPage
	 */
	private function getMockSpecialPage() {
		$mock = $this->createMock( SpecialPage::class );
		$mock->method( 'getPageTitle' )
			->willReturn( Title::newFromText( 'SomeTitle' ) );
		$mock->method( 'getRequest' )
			->willReturn( new FauxRequest( [ 'importDetailsHash' => 'FAKEHASH' ] ) );
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
			[ 'Loo', null, 'Loo' ],
			[ 'Loo', 'Foo', 'Foo' ],
			[ 'Loo', 'Foooo/Barr', 'Foooo/Barr' ],
		];
	}

	/**
	 * @dataProvider provideTestTextDisplayedInInputBox
	 * @param string $userInputName
	 * @param string $expectedInputText
	 */
	public function testTextDisplayedInInputBox( $fileName, $userInputName, $expectedInputText ) {
		$importPlan = $this->createMock( ImportPlan::class );
		$importPlan->method( 'getRequest' )
			->willReturn( new ImportRequest( 'http://goog', $userInputName, '' ) );
		$importPlan->method( 'getDetails' )
			->willReturn( $this->getMockImportDetails() );
		$importPlan->method( 'getFileName' )
			->willReturn( $fileName );

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
