<?php

namespace FileImporter\Test;

use FauxRequest;
use FileImporter\MediaWiki\SiteTableSiteLookup;
use FileImporter\SpecialImportFile;
use HashSiteStore;
use PermissionsError;
use Site;
use SpecialPage;
use SpecialPageTestBase;
use User;
use WebResponse;

/**
 * This test makes some calls to https://commons.wikimedia.org
 *
 * @group Database
 */
class SpecialImportFileIntegrationTest extends SpecialPageTestBase {

	public function setUp() {
		parent::setUp();

		$this->setMwGlobals( 'wgEnableUploads', true );

		$commonsSite = $this->getMockSite( 'commonswiki', 'commons.wikimedia.org' );
		$hashSiteStore = new HashSiteStore( [ $commonsSite ] );
		$siteTableSiteLookup = new SiteTableSiteLookup( $hashSiteStore );
		$this->setService( 'FileImporterMediaWikiSiteTableSiteLookup', $siteTableSiteLookup );
		$this->tablesUsed[] = 'user_groups';
	}

	private function getMockSite( $globalId, $domain ) {
		$mockSite = $this->getMock( Site::class );
		$mockSite->expects( $this->any() )
			->method( 'getGlobalId' )
			->will( $this->returnValue( $globalId ) );
		$mockSite->expects( $this->any() )
			->method( 'getDomain' )
			->will( $this->returnValue( $domain ) );
		$mockSite->expects( $this->any() )
			->method( 'getNavigationIds' )
			->will( $this->returnValue( [] ) );
		return $mockSite;
	}

	/**
	 * Returns a new instance of the special page under test.
	 *
	 * @return SpecialPage
	 */
	protected function newSpecialPage() {
		return new SpecialImportFile();
	}

	public function provideTestData() {
		return [
			'Anon user, Expect Groups required' => [
				new FauxRequest(),
				new User(),
				[
					'name' => PermissionsError::class,
					'message' => 'The action you have requested is limited to users in one of the groups',
				],
				function (){
				}
			],
			'Uploader, Expect input form' => [
				new FauxRequest(),
				true,
				null,
				function ( $html ) {
					$this->assertInitialInputFormPreset( $html );
				}
			],
			'Bad domain (not in allowed sites)' => [
				new FauxRequest( [
					'clientUrl' => 'https://test.wikimedia.org/wiki/File:AnyFile.JPG'
				] ),
				true,
				null,
				function ( $html ) {
					$this->assertInitialInputFormPreset( $html );
					$this->assertWarningBox( $html, 'Can\'t import the given URL' );
				}
			],
			'Bad domain (malformed?)' => [
				new FauxRequest( [
					'clientUrl' => 't243ju89gujwe9fjka09jg'
				] ),
				true,
				null,
				function ( $html ) {
					$this->assertInitialInputFormPreset( $html );
					$this->assertWarningBox( $html, 'Can\'t parse the given URL: t243ju89gujwe9fjka09jg' );
				}
			],
			'Bad file' => [
				new FauxRequest( [
					'clientUrl' => 'https://commons.wikimedia.org/wiki/ThisIsNotAFileFooBarBarBar'
				] ),
				true,
				null,
				function ( $html ) {
					$this->assertInitialInputFormPreset( $html );
					$this->assertWarningBox(
						$html,
						'File not found: https://commons.wikimedia.org/wiki/ThisIsNotAFileFooBarBarBar'
					);
				}
			],
			'Good file' => [
				new FauxRequest( [
					'clientUrl' => 'https://commons.wikimedia.org/wiki/File:Chicken_In_Snow.JPG',
				] ),
				true,
				null,
				function ( $html ) {
					$this->assertPreviewPage(
						$html,
						'https://commons.wikimedia.org/wiki/File:Chicken_In_Snow.JPG',
						'Chicken In Snow'
					);
					$this->assertTagExistsWithTextContents(
						$html,
						'h2',
						'Chicken In Snow'
					);
				}
			],
			'Good file & Good target title' => [
				new FauxRequest( [
					'clientUrl' => 'https://commons.wikimedia.org/wiki/File:Chicken_In_Snow.JPG',
					'intendedFileName' => 'Chicken In Snow CHANGED',
					'importDetailsHash' => 'SomeHash',// XXX: This is currently not checked?
				] ),
				true,
				null,
				function ( $html ) {
					$this->assertPreviewPage(
						$html,
						'https://commons.wikimedia.org/wiki/File:Chicken_In_Snow.JPG',
						'Chicken In Snow CHANGED'
					);
					$this->assertTagExistsWithTextContents(
						$html,
						'h2',
						'Chicken In Snow CHANGED'
					);
				}
			],
		];
	}

	private function assertTagExistsWithTextContents( $html, $tagName, $value ) {
		assertThat(
			$html,
			is( htmlPiece( havingChild( both(
				withTagName( $tagName ) )
				->andAlso( havingTextContents( $value ) )
			) ) )
		);
	}

	private function assertInitialInputFormPreset( $html ) {
		assertThat(
			$html,
			is( htmlPiece( havingChild(
						both( withTagName( 'form' ) )
							->andAlso( withAttribute( 'action' ) )
							->andAlso( withAttribute( 'method' )->havingValue( 'GET' ) )
							->andAlso( havingChild(
									both( withTagName( 'input' ) )
										->andAlso( withAttribute( 'type' )->havingValue( 'url' ) )
										->andAlso( withAttribute( 'name' )->havingValue( 'clientUrl' ) )
							) )
							->andAlso( havingChild(
									both( withTagName( 'button' ) )
										->andAlso( withAttribute( 'type' )->havingValue( 'submit' ) )
							) )
			) ) )
		);
	}

	private function assertWarningBox( $html, $text ) {
		assertThat(
			$html,
			is( htmlPiece( havingChild(
				both( withTagName( 'div' ) )
					->andAlso( withClass( 'warningbox' ) )
					->andAlso( havingChild(
						both( withTagName( 'p' ) )
							->andAlso( havingTextContents( $text ) )
					) )
			) ) )
		);
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
					->andAlso( havingChild( $this->thatIsHiddenInputFieldWithSomeValue( 'token' ) ) )
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
	 * @dataProvider provideTestData
	 */
	public function testSpecialPageExecutionWithVariousInputs(
		$request,
		$userOrBool,
		$expectedExceptionDetails = null,
		$htmlAssertionCallable
	) {
		if ( $expectedExceptionDetails ) {
			$this->setExpectedException(
				$expectedExceptionDetails['name'],
				$expectedExceptionDetails['message']
			);
		}

		if ( $userOrBool instanceof User ) {
			$user = $userOrBool;
		} elseif ( $userOrBool ) {
			$user = $this->getTestSysop()->getUser();
		} else {
			$user = $this->getTestUser()->getUser();
		}

		/** @var string $html */
		/** @var WebResponse $response */
		list( $html, $response ) = $this->executeSpecialPage(
			'',
			$request,
			'en',
			$user
		);

		$htmlAssertionCallable( $html );
		$this->assertTrue( true );// assertion to avoid phpunit showing hamcrest test as risky
	}

}
