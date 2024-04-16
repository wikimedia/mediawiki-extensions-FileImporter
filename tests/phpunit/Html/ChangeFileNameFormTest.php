<?php

namespace FileImporter\Tests\Html;

use FileImporter\Data\ImportDetails;
use FileImporter\Data\ImportPlan;
use FileImporter\Data\ImportRequest;
use FileImporter\Data\TextRevision;
use FileImporter\Data\TextRevisions;
use FileImporter\Html\ChangeFileNameForm;
use HamcrestPHPUnitIntegration;
use MediaWiki\Language\RawMessage;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\Title;
use OOUI\BlankTheme;
use OOUI\Theme;
use RequestContext;

/**
 * @covers \FileImporter\Html\ChangeFileNameForm
 * @covers \FileImporter\Html\ImportIdentityFormSnippet
 *
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class ChangeFileNameFormTest extends \MediaWikiIntegrationTestCase {
	use HamcrestPHPUnitIntegration;

	protected function setUp(): void {
		parent::setUp();
		Theme::setSingleton( new BlankTheme() );
	}

	protected function tearDown(): void {
		Theme::setSingleton();
		parent::tearDown();
	}

	private function getMockSpecialPage(): SpecialPage {
		$context = $this->createMock( RequestContext::class );
		$context->method( 'msg' )
			->willReturn( new RawMessage( '' ) );

		$mock = $this->createMock( SpecialPage::class );
		$mock->method( 'getPageTitle' )
			->willReturn( Title::makeTitle( NS_MAIN, __METHOD__ ) );
		$mock->method( 'getContext' )
			->willReturn( $context );
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

	public static function provideTestTextDisplayedInInputBox() {
		return [
			[ 'Loo', 'Loo' ],
			[ 'Foooo/Barr', 'Foooo/Barr' ],
		];
	}

	/**
	 * @dataProvider provideTestTextDisplayedInInputBox
	 */
	public function testTextDisplayedInInputBox( string $fileName, string $expectedInputText ) {
		$importPlan = $this->createMock( ImportPlan::class );
		$importPlan->method( 'getRequest' )
			->willReturn( new ImportRequest( '//w.invalid' ) );
		$importPlan->method( 'getDetails' )
			->willReturn( $this->getMockImportDetails() );
		$importPlan->method( 'getFileName' )
			->willReturn( $fileName );
		$importPlan->method( 'getActionStats' )
			->willReturn( [] );
		$importPlan->method( 'getValidationWarnings' )
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
					->andAlso( havingChild(
						both( withTagName( 'input' ) )
							->andAlso( withAttribute( 'name' )->havingValue( 'validationWarnings' ) )
							->andAlso( withAttribute( 'value' )->havingValue( '[]' ) )
					) )
			) ) )
		);
	}

}
