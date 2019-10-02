<?php

namespace FileImporter\Tests\Html;

use FauxRequest;
use FileImporter\Data\ImportDetails;
use FileImporter\Data\ImportPlan;
use FileImporter\Data\ImportRequest;
use FileImporter\Data\TextRevision;
use FileImporter\Data\TextRevisions;
use FileImporter\Html\ChangeFileNameForm;
use OOUI\BlankTheme;
use OOUI\Theme;
use PHPUnit4And6Compat;
use SpecialPage;
use Title;

/**
 * @covers \FileImporter\Html\ChangeFileNameForm
 * @covers \FileImporter\Html\ImportIdentityFormSnippet
 *
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class ChangeFileNameFormTest extends \PHPUnit\Framework\TestCase {
	use \HamcrestPHPUnitIntegration;
	use PHPUnit4And6Compat;

	public function setUp() {
		parent::setUp();
		Theme::setSingleton( new BlankTheme() );
	}

	public function tearDown() {
		Theme::setSingleton( null );
		parent::tearDown();
	}

	private function getMockSpecialPage() : SpecialPage {
		$mock = $this->createMock( SpecialPage::class );
		$mock->method( 'getPageTitle' )
			->willReturn( Title::newFromText( __METHOD__ ) );
		$mock->method( 'getRequest' )
			->willReturn( new FauxRequest( [ 'importDetailsHash' => 'FAKEHASH' ] ) );
		$mock->method( 'msg' )
			->willReturn( new \RawMessage( '' ) );
		return $mock;
	}

	private function getMockImportDetails() : ImportDetails {
		$mock = $this->createMock( ImportDetails::class );
		$mock->method( 'getTextRevisions' )
			->willReturn( $this->getMockTextRevisions() );
		return $mock;
	}

	private function getMockTextRevisions() : TextRevisions {
		$mock = $this->createMock( TextRevisions::class );
		$mock->method( 'getLatest' )
			->willReturn( $this->getMockTextRevision() );
		return $mock;
	}

	private function getMockTextRevision() : TextRevision {
		$mock = $this->createMock( TextRevision::class );
		$mock->method( 'getField' )
			->willReturn( '' );
		return $mock;
	}

	public function provideTestTextDisplayedInInputBox() {
		return [
			[ 'Loo', 'Loo' ],
			[ 'Foooo/Barr', 'Foooo/Barr' ],
		];
	}

	/**
	 * @dataProvider provideTestTextDisplayedInInputBox
	 * @param string $fileName
	 * @param string $expectedInputText
	 */
	public function testTextDisplayedInInputBox( $fileName, $expectedInputText ) {
		$importPlan = $this->createMock( ImportPlan::class );
		$importPlan->method( 'getRequest' )
			->willReturn( new ImportRequest( '//w.invalid' ) );
		$importPlan->method( 'getDetails' )
			->willReturn( $this->getMockImportDetails() );
		$importPlan->method( 'getFileName' )
			->willReturn( $fileName );
		$importPlan->method( 'getActionStats' )
			->willReturn( [] );

		$form = new ChangeFileNameForm( $this->getMockSpecialPage() );

		$this->assertThatHamcrest(
			$form->getHtml( $importPlan ),
			is( htmlPiece( havingChild(
				both( withTagName( 'form' ) )
					->andAlso( havingChild(
						both( withTagName( 'input' ) )
							->andAlso( withAttribute( 'name' )->havingValue( 'intendedFileName' ) )
							->andAlso( withAttribute( 'value' )->havingValue( $expectedInputText ) )
					) )
					->andAlso( havingChild(
						both( withTagName( 'input' ) )
							->andAlso( withAttribute( 'name' )->havingValue( 'actionStats' ) )
							->andAlso( withAttribute( 'value' )->havingValue( '[]' ) )
					) )
			) ) )
		);
	}

}
