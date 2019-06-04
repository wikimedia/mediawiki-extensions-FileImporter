<?php

namespace FileImporter\Html\Test;

use FileImporter\Data\FileRevision;
use FileImporter\Data\FileRevisions;
use FileImporter\Data\ImportDetails;
use FileImporter\Data\ImportPlan;
use FileImporter\Data\ImportRequest;
use FileImporter\Data\TextRevision;
use FileImporter\Data\TextRevisions;
use FileImporter\Html\ImportPreviewPage;
use OOUI\BlankTheme;
use OOUI\Theme;
use SpecialPage;
use Title;
use User;

/**
 * @covers \FileImporter\Html\ImportPreviewPage
 */
class ImportPreviewPageTest extends \MediaWikiLangTestCase {

	const CLIENT_URL = '//w.invalid/';
	const NAME = 'Chicken In Snow';
	const INITIAL_TEXT = 'Foo';
	const HASH = 'ORIGINAL_HASH';

	public function setUp() {
		parent::setUp();

		Theme::setSingleton( new BlankTheme() );
	}

	public function providePlanContent() {
		yield [ self::INITIAL_TEXT, 0 ];
		yield [ self::INITIAL_TEXT, 1 ];
		yield [ 'Bar', 0 ];
		yield [ 'Bar', 1 ];
	}

	/**
	 * @dataProvider providePlanContent
	 */
	public function testGetHtml( $submittedText, $replacements ) {
		$this->setContentLang( 'qqx' );

		$importPlan = new ImportPlan(
			new ImportRequest( self::CLIENT_URL, self::NAME, self::INITIAL_TEXT ),
			$this->getMockImportDetails( $submittedText ),
			''
		);
		$importPlan->setNumberOfTemplateReplacements( $replacements );

		$page = new ImportPreviewPage( $this->getMockSpecialPage() );
		$html = $page->getHtml( $importPlan );

		$this->assertPreviewPageText( $html );
		$this->assertPreviewPageForm( $html );
		$this->assertSummary( $html, $submittedText, $replacements );

		// Without this line, PHPUnit doesn't count Hamcrest assertions and marks the test as risky.
		$this->addToAssertionCount( 1 );
	}

	private function assertPreviewPageText( $html ) {
		assertThat(
			$html,
			is( htmlPiece( havingChild(
				both( withTagName( 'div' ) )
					->andAlso( withAttribute( 'class' )->havingValue( 'mw-importfile-parsedContent' ) )
			) ) )
		);
	}

	private function assertPreviewPageForm( $html ) {
		assertThat(
			$html,
			is( htmlPiece( havingChild(
				both( withTagName( 'form' ) )
					->andAlso( withAttribute( 'action' ) )
					->andAlso( withAttribute( 'method' )->havingValue( 'POST' ) )
					->andAlso( havingChild( $this->thatIsInputField( 'clientUrl', self::CLIENT_URL ) ) )
					->andAlso(
						havingChild( $this->thatIsInputField( 'intendedFileName', self::NAME ) )
					)
					->andAlso( havingChild( $this->thatIsInputField(
						'importDetailsHash',
						self::HASH
					) ) )
					->andAlso( havingChild( $this->thatIsInputFieldWithSomeValue( 'token' ) ) )
					->andAlso( havingChild( $this->thatIsInputField( 'action', 'submit' ) ) )
					->andAlso( havingChild(
						both( withTagName( 'button' ) )
							->andAlso( withAttribute( 'type' )->havingValue( 'submit' ) )
						) )
			) ) )
		);
	}

	private function assertSummary( $html, $submittedText, $replacements ) {
		if ( $replacements > 0 ) {
			assertThat(
				$html,
				is( htmlPiece( havingChild(
					both( withTagName( 'form' ) )
						->andAlso( havingChild( $this->thatIsInputField(
							'intendedRevisionSummary',
							'(fileimporter-auto-replacements-summary: ' . $replacements . ')'
						) ) )
				) ) )
			);
		} elseif ( $submittedText !== self::INITIAL_TEXT ) {
			assertThat(
				$html,
				is( htmlPiece( havingChild(
					both( withTagName( 'form' ) )
						->andAlso( havingChild( $this->thatIsInputField(
							'intendedRevisionSummary',
							''
						) ) )
				) ) )
			);
		} else {
			assertThat(
				$html,
				is( not( htmlPiece( havingChild( $this->thatIsInputFieldWithSomeValue(
					'intendedRevisionSummary'
				) ) ) ) )
			);
		}
	}

	private function thatIsInputField( $name, $value ) {
		return both( withTagName( 'input' ) )
			->andAlso( withAttribute( 'name' )->havingValue( $name ) )
			->andAlso( withAttribute( 'value' )->havingValue( $value ) );
	}

	private function thatIsInputFieldWithSomeValue( $name ) {
		return both( withTagName( 'input' ) )
			->andAlso( withAttribute( 'name' )->havingValue( $name ) )
			->andAlso( withAttribute( 'value' ) );
	}

	/**
	 * @return SpecialPage
	 */
	private function getMockSpecialPage() {
		$user = $this->createMock( User::class );
		$user->method( 'getEditToken' )
			->willReturn( '123' );

		$mock = $this->createMock( SpecialPage::class );
		$mock->method( 'getPageTitle' )
			->willReturn( Title::newFromText( __METHOD__ ) );
		$mock->method( 'getUser' )
			->willReturn( $user );
		$mock->method( 'msg' )
			->willReturnCallback( 'wfMessage' );
		return $mock;
	}

	/**
	 * @return ImportDetails
	 */
	private function getMockImportDetails( $wikitext ) {
		$mock = $this->createMock( ImportDetails::class );
		$mock->method( 'getTextRevisions' )
			->willReturn( $this->getMockTextRevisions( $wikitext, self::NAME ) );
		$mock->method( 'getFileRevisions' )
			->willReturn( $this->getMockFileRevisions() );
		$mock->method( 'getOriginalHash' )
			->willReturn( self::HASH );
		return $mock;
	}

	/**
	 * @return TextRevisions
	 */
	private function getMockTextRevisions( $wikitext ) {
		$mockTextRevision = $this->getMockTextRevision( $wikitext, self::NAME );
		$mock = $this->createMock( TextRevisions::class );
		$mock->method( 'getLatest' )
			->willReturn( $mockTextRevision );
		$mock->method( 'toArray' )
			->willReturn( [ $mockTextRevision ] );
		return $mock;
	}

	/**
	 * @return TextRevision
	 */
	private function getMockTextRevision( $wikitext, $title ) {
		$mock = $this->createMock( TextRevision::class );
		$mock->method( 'getField' )
			->willReturnCallback( function ( $field ) use ( $wikitext, $title ) {
				switch ( $field ) {
					case '*':
						return $wikitext;
					case 'title':
						return $title;
					case 'contentmodel':
						return CONTENT_MODEL_WIKITEXT;
					case 'contentformat':
						return CONTENT_FORMAT_WIKITEXT;
					default:
						return '';
				}
			} );
		return $mock;
	}

	/**
	 * @return FileRevisions
	 */
	private function getMockFileRevisions() {
		$mockFileRevision = $this->getMockFileRevision();
		$mock = $this->createMock( FileRevisions::class );
		$mock->method( 'getLatest' )
			->willReturn( $mockFileRevision );
		$mock->method( 'toArray' )
			->willReturn( [ $mockFileRevision ] );
		return $mock;
	}

	/**
	 * @return FileRevision
	 */
	private function getMockFileRevision() {
		$mock = $this->createMock( FileRevision::class );
		return $mock;
	}

}
