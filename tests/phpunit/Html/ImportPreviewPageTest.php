<?php

namespace FileImporter\Tests\Html;

use FileImporter\Data\FileRevision;
use FileImporter\Data\FileRevisions;
use FileImporter\Data\ImportDetails;
use FileImporter\Data\ImportPlan;
use FileImporter\Data\ImportRequest;
use FileImporter\Data\TextRevision;
use FileImporter\Data\TextRevisions;
use FileImporter\Html\ImportPreviewPage;
use FileImporter\Remote\MediaWiki\RemoteApiActionExecutor;
use FileImporter\Services\WikidataTemplateLookup;
use Hamcrest\Matcher;
use HamcrestPHPUnitIntegration;
use MediaWiki\Config\HashConfig;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\MainConfigNames;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use MessageLocalizer;
use OOUI\BlankTheme;
use OOUI\Theme;
use StatusValue;

/**
 * @covers \FileImporter\Html\ImportPreviewPage
 *
 * @group Database
 * @license GPL-2.0-or-later
 */
class ImportPreviewPageTest extends \MediaWikiLangTestCase {
	use HamcrestPHPUnitIntegration;

	private const CLIENT_URL = '//w.invalid/';
	private const NAME = 'Chicken In Snow';
	private const INITIAL_TEXT = 'Foo';
	private const HASH = 'ORIGINAL_HASH';

	protected function setUp(): void {
		parent::setUp();

		Theme::setSingleton( new BlankTheme() );
	}

	public static function providePlanContent() {
		yield [ self::INITIAL_TEXT, 0 ];
		yield [ self::INITIAL_TEXT, 1 ];
		yield [ 'Bar', 0 ];
		yield [ 'Bar', 1 ];
	}

	/**
	 * @dataProvider providePlanContent
	 */
	public function testGetHtml( string $submittedText, int $replacements ) {
		$this->overrideConfigValue( MainConfigNames::LanguageCode, 'qqx' );

		$importPlan = new ImportPlan(
			new ImportRequest( self::CLIENT_URL, self::NAME, self::INITIAL_TEXT ),
			$this->getMockImportDetails( $submittedText ),
			new HashConfig(),
			$this->createMock( MessageLocalizer::class ),
			''
		);
		$importPlan->setNumberOfTemplateReplacements( $replacements );

		$page = new ImportPreviewPage( $this->getMockSpecialPage() );
		$html = $page->getHtml( $importPlan );

		$this->assertPreviewPageText( $html );
		$this->assertPreviewPageForm( $html );
		$this->assertSummary( $html, $submittedText, $replacements );
	}

	public function testGetHtml_doNotEditSourceWiki() {
		$this->setMwGlobals( [
			'wgFileImporterSourceWikiTemplating' => false,
		] );
		$importPlan = new ImportPlan(
			new ImportRequest( self::CLIENT_URL, self::NAME, self::INITIAL_TEXT ),
			$this->getMockImportDetails( 'Bar' ),
			new HashConfig(),
			$this->createMock( MessageLocalizer::class ),
			''
		);
		$importPlan->setNumberOfTemplateReplacements( 0 );

		$page = new ImportPreviewPage( $this->getMockSpecialPage() );
		$html = $page->getHtml( $importPlan );

		$this->assertStringNotContainsString( 'automateSourceWikiCleanup', $html );
	}

	public function testGetHtml_canEditSourceWiki() {
		if ( !\ExtensionRegistry::getInstance()->isLoaded( 'CentralAuth' ) ) {
			$this->markTestSkipped( 'CentralAuth not loaded' );
		}

		$this->setMwGlobals( [
			'wgFileImporterSourceWikiTemplating' => true,
		] );
		$this->setService( 'FileImporterTemplateLookup', $this->getMockTemplateLookup() );
		$api = $this->createMock( RemoteApiActionExecutor::class );
		$api->method( 'executeTestEditActionQuery' )
			->willReturn( StatusValue::newGood() );
		$this->setService( 'FileImporterMediaWikiRemoteApiActionExecutor', $api );
		$importPlan = new ImportPlan(
			new ImportRequest( self::CLIENT_URL, self::NAME, self::INITIAL_TEXT ),
			$this->getMockImportDetails( 'Bar' ),
			new HashConfig(),
			$this->createMock( MessageLocalizer::class ),
			''
		);

		$page = new ImportPreviewPage( $this->getMockSpecialPage() );
		$html = $page->getHtml( $importPlan );

		$this->assertSelectedCheckbox( $html, 'automateSourceWikiCleanup' );
	}

	private function getMockTemplateLookup(): WikidataTemplateLookup {
		$mock = $this->createMock( WikidataTemplateLookup::class );
		$mock->method( 'fetchNowCommonsLocalTitle' )
			->willReturn( 'TestNowCommons' );
		return $mock;
	}

	private function assertPreviewPageText( string $html ): void {
		$this->assertStringContainsString( '<div class="mw-importfile-parsedContent">', $html );
	}

	private function assertPreviewPageForm( string $html ): void {
		$this->assertThatHamcrest(
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
					->andAlso( havingChild( $this->thatIsInputField(
						'actionStats',
						'[]'
					) ) )
					->andAlso( havingChild( $this->thatIsInputFieldWithSomeValue( 'token' ) ) )
					->andAlso( havingChild(
						both( withTagName( 'button' ) )
							->andAlso( withAttribute( 'type' )->havingValue( 'submit' ) )
							->andAlso( withAttribute( 'name' )->havingValue( 'action' ) )
							->andAlso( withAttribute( 'value' )->havingValue( 'submit' ) )
						) )
			) ) )
		);
	}

	private function assertSelectedCheckbox( string $html, string $name ): void {
		$this->assertStringContainsString( " name='$name' value='1' checked='checked'", $html );
	}

	private function assertSummary( string $html, string $submittedText, int $replacements ): void {
		if ( $replacements > 0 ) {
			$this->assertStringContainsString( " name='intendedRevisionSummary'" .
				" value='(fileimporter-auto-replacements-summary: $replacements)'", $html );
		} elseif ( $submittedText !== self::INITIAL_TEXT ) {
			$this->assertStringContainsString( " name='intendedRevisionSummary' value=''", $html );
		} else {
			$this->assertStringNotContainsString( 'intendedRevisionSummary', $html );
		}
	}

	private function thatIsInputField( string $name, string $value ): Matcher {
		return both( withTagName( 'input' ) )
			->andAlso( withAttribute( 'name' )->havingValue( $name ) )
			->andAlso( withAttribute( 'value' )->havingValue( $value ) );
	}

	private function thatIsInputFieldWithSomeValue( string $name ): Matcher {
		return both( withTagName( 'input' ) )
			->andAlso( withAttribute( 'name' )->havingValue( $name ) );
	}

	private function getMockSpecialPage(): SpecialPage {
		$context = $this->createMock( \IContextSource::class );
		$context->method( 'getUser' )
			->willReturn( $this->createMock( User::class ) );
		$context->method( 'getConfig' )
			->willReturn( new HashConfig( [
				'FileImporterSourceWikiTemplating' => true,
				'FileImporterSourceWikiDeletion' => true,
			] ) );
		$context->method( 'msg' )
			->willReturnCallback( 'wfMessage' );

		$mock = $this->createMock( SpecialPage::class );
		$mock->method( 'getPageTitle' )
			->willReturn( Title::makeTitle( NS_MAIN, __METHOD__ ) );
		$mock->method( 'getContext' )
			->willReturn( $context );
		return $mock;
	}

	private function getMockImportDetails( string $wikitext ): ImportDetails {
		$mock = $this->createMock( ImportDetails::class );
		$mock->method( 'getSourceLinkTarget' )
			->willReturn( $this->createMock( LinkTarget::class ) );
		$mock->method( 'getTextRevisions' )
			->willReturn( $this->getMockTextRevisions( $wikitext ) );
		$mock->method( 'getFileRevisions' )
			->willReturn( $this->getMockFileRevisions() );
		$mock->method( 'getOriginalHash' )
			->willReturn( self::HASH );
		return $mock;
	}

	private function getMockTextRevisions( string $wikitext ): TextRevisions {
		$mockTextRevision = $this->getMockTextRevision( $wikitext, self::NAME );
		$mock = $this->createMock( TextRevisions::class );
		$mock->method( 'getLatest' )
			->willReturn( $mockTextRevision );
		$mock->method( 'toArray' )
			->willReturn( [ $mockTextRevision ] );
		return $mock;
	}

	private function getMockTextRevision( string $wikitext, string $title ): TextRevision {
		$mock = $this->createMock( TextRevision::class );
		$mock->method( 'getField' )
			->willReturnCallback( static function ( string $field ) use ( $title ): string {
				switch ( $field ) {
					case 'title':
						return $title;
					default:
						return '';
				}
			} );
		$mock->method( 'getContent' )->willReturn( $wikitext );
		$mock->method( 'getContentFormat' )->willReturn( CONTENT_FORMAT_WIKITEXT );
		$mock->method( 'getContentModel' )->willReturn( CONTENT_MODEL_WIKITEXT );
		return $mock;
	}

	private function getMockFileRevisions(): FileRevisions {
		$mockFileRevision = $this->getMockFileRevision();
		$mock = $this->createMock( FileRevisions::class );
		$mock->method( 'getLatest' )
			->willReturn( $mockFileRevision );
		$mock->method( 'toArray' )
			->willReturn( [ $mockFileRevision ] );
		return $mock;
	}

	private function getMockFileRevision(): FileRevision {
		$mock = $this->createMock( FileRevision::class );
		return $mock;
	}

}
