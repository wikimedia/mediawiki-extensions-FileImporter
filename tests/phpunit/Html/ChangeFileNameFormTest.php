<?php

namespace FileImporter\Tests\Html;

use FileImporter\Data\ImportPlan;
use FileImporter\Html\ChangeFileNameForm;
use HamcrestPHPUnitIntegration;
use MediaWiki\Language\RawMessage;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\Title;
use MessageLocalizer;

/**
 * @covers \FileImporter\Html\ChangeFileNameForm
 * @covers \FileImporter\Html\ImportIdentityFormSnippet
 *
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class ChangeFileNameFormTest extends \MediaWikiIntegrationTestCase {
	use HamcrestPHPUnitIntegration;

	private function getMockSpecialPage(): SpecialPage {
		$context = $this->createMock( MessageLocalizer::class );
		$context->method( 'msg' )
			->willReturn( new RawMessage( '' ) );

		$mock = $this->createNoOpMock( SpecialPage::class, [ 'getPageTitle', 'getContext' ] );
		$mock->method( 'getPageTitle' )
			->willReturn( Title::makeTitle( NS_MAIN, __METHOD__ ) );
		$mock->method( 'getContext' )
			->willReturn( $context );
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
		$importPlan->method( 'getFileName' )
			->willReturn( $fileName );

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
