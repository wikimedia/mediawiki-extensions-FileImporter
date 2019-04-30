<?php

namespace FileImporter\Html\Test;

use FauxRequest;
use FileImporter\Data\FileRevision;
use FileImporter\Data\FileRevisions;
use FileImporter\Data\ImportDetails;
use FileImporter\Data\ImportPlan;
use FileImporter\Data\ImportRequest;
use FileImporter\Data\TextRevision;
use FileImporter\Data\TextRevisions;
use FileImporter\Html\ImportPreviewPage;
use IContextSource;
use Language;
use OOUI\BlankTheme;
use OOUI\Theme;
use OutputPage;
use SpecialPage;
use Title;
use User;

/**
 * @coversDefaultClass \FileImporter\Html\ImportPreviewPage
 */
class ImportPreviewPageTest extends \MediaWikiTestCase {

	public function setUp() {
		parent::setUp();

		Theme::setSingleton( new BlankTheme() );
	}

	/**
	 * @covers ::getHtml
	 */
	public function testGetHtml() {
		$clientUrl = 'https://commons.wikimedia.org/wiki/File:Chicken_In_Snow.JPG';
		$dbkey = 'Chicken_In_Snow';
		$fileName = 'Chicken In Snow';
		$wikitext = 'foo';

		$importPlan = new ImportPlan(
			new ImportRequest( $clientUrl, $fileName, $wikitext ),
			$this->getMockImportDetails( $wikitext, $dbkey ),
			''
		);

		$page = new ImportPreviewPage( $this->getMockSpecialPage() );
		$html = $page->getHtml( $importPlan );

		$this->assertPreviewPage( $html, $clientUrl, $fileName );

		// Without this line, PHPUnit doesn't count Hamcrest assertions and marks the test as risky.
		$this->addToAssertionCount( 1 );
	}

	private function assertPreviewPage( $html, $clientUrl, $intendedFileName ) {
		assertThat(
			$html,
			is( htmlPiece( havingChild(
				both( withTagName( 'form' ) )
					->andAlso( withAttribute( 'action' ) )
					->andAlso( withAttribute( 'method' )->havingValue( 'POST' ) )
					->andAlso( havingChild( $this->thatIsHiddenInputField( 'clientUrl', $clientUrl ) ) )
					->andAlso(
						havingChild( $this->thatIsHiddenInputField( 'intendedFileName', $intendedFileName ) )
					)
					->andAlso( havingChild( $this->thatIsHiddenInputFieldWithSomeValue( 'importDetailsHash' ) ) )
					// FIXME: Don't know why the token isn't being passed through.
					// ->andAlso( havingChild( $this->thatIsHiddenInputFieldWithSomeValue( 'token' ) ) )
					->andAlso( havingChild( $this->thatIsHiddenInputField( 'action', 'submit' ) ) )
					->andAlso( havingChild(
						both( withTagName( 'button' ) )
							->andAlso( withAttribute( 'type' )->havingValue( 'submit' ) )
						) )
			) ) )
		);
	}

	private function thatIsHiddenInputField( $name, $value ) {
		return both( withTagName( 'input' ) )
			->andAlso( withAttribute( 'type' )->havingValue( 'hidden' ) )
			->andAlso( withAttribute( 'name' )->havingValue( $name ) )
			->andAlso( withAttribute( 'value' )->havingValue( $value ) );
	}

	private function thatIsHiddenInputFieldWithSomeValue( $name ) {
		return both( withTagName( 'input' ) )
			->andAlso( withAttribute( 'type' )->havingValue( 'hidden' ) )
			->andAlso( withAttribute( 'name' )->havingValue( $name ) )
			->andAlso( withAttribute( 'value' ) );
	}

	/**
	 * @return SpecialPage
	 */
	private function getMockSpecialPage() {
		$user = $this->createMock( User::class );
		$user->method( 'matchEditToken' )
			->willReturn( '123' );

		$request = new FauxRequest( [ 'importDetailsHash' => 'FAKEHASH' ] );

		$output = $this->createMock( OutputPage::class );
		$output->method( 'getRequest' )
			->willReturn( $request );

		$context = $this->createMock( IContextSource::class );
		$context->method( 'getUser' )
			->willReturn( $user );

		$mock = $this->createMock( SpecialPage::class );
		$mock->method( 'getPageTitle' )
			->willReturn( Title::newFromText( __METHOD__ ) );
		$mock->method( 'getContext' )
			->willReturn( $context );
		$mock->method( 'getRequest' )
			->willReturn( $request );
		$mock->method( 'getOutput' )
			->willReturn( $output );
		$mock->method( 'getUser' )
			->willReturn( $user );
		$mock->method( 'getLanguage' )
			->willReturn( $this->createMock( Language::class ) );
		$mock->method( 'msg' )
			->willReturn( new \RawMessage( '' ) );
		return $mock;
	}

	/**
	 * @return ImportDetails
	 */
	private function getMockImportDetails( $wikitext, $title ) {
		$mock = $this->createMock( ImportDetails::class );
		$mock->method( 'getTextRevisions' )
			->willReturn( $this->getMockTextRevisions( $wikitext, $title ) );
		$mock->method( 'getFileRevisions' )
			->willReturn( $this->getMockFileRevisions() );
		$mock->method( 'getOriginalHash' )
			->willReturn( 'ORIGINAL_HASH' );
		return $mock;
	}

	/**
	 * @return TextRevisions
	 */
	private function getMockTextRevisions( $wikitext, $title ) {
		$mockTextRevision = $this->getMockTextRevision( $wikitext, $title );
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
