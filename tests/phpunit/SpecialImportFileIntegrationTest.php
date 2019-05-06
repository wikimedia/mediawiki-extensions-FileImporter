<?php

namespace FileImporter\Test;

use FauxRequest;
use FileImporter\Exceptions\HttpRequestException;
use FileImporter\Remote\MediaWiki\SiteTableSiteLookup;
use FileImporter\Services\Http\HttpRequestExecutor;
use FileImporter\SpecialImportFile;
use HashSiteStore;
use PermissionsError;
use Site;
use SpecialPage;
use SpecialPageTestBase;
use User;
use WebRequest;

/**
 * @coversNothing
 *
 * FIXME: This test makes some calls to https://commons.wikimedia.org
 *
 * @group Database
 *
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class SpecialImportFileIntegrationTest extends SpecialPageTestBase {

	private static $hasAccessToCommons;

	public static function setUpBeforeClass() {
		parent::setUpBeforeClass();
		try {
			$requestExecutor = new HttpRequestExecutor( [], 0 );
			$requestExecutor->execute( 'https://commons.wikimedia.org' );
			self::$hasAccessToCommons = true;
		} catch ( HttpRequestException $e ) {
			self::$hasAccessToCommons = false;
		}
	}

	public function setUp() {
		parent::setUp();

		$this->setMwGlobals( [
			'wgEnableUploads' => true ,
			'wgFileImporterShowInputScreen' => true,
		] );

		$commonsSite = $this->getMockSite( 'commonswiki', 'commons.wikimedia.org' );
		$hashSiteStore = new HashSiteStore( [ $commonsSite ] );
		$siteTableSiteLookup = new SiteTableSiteLookup( $hashSiteStore );
		$this->setService( 'FileImporterMediaWikiSiteTableSiteLookup', $siteTableSiteLookup );
		$this->tablesUsed[] = 'user_groups';
	}

	/**
	 * @param string $globalId
	 * @param string $domain
	 *
	 * @return Site
	 */
	private function getMockSite( $globalId, $domain ) {
		$mockSite = $this->createMock( Site::class );
		$mockSite->method( 'getGlobalId' )
			->willReturn( $globalId );
		$mockSite->method( 'getDomain' )
			->willReturn( $domain );
		$mockSite->method( 'getNavigationIds' )
			->willReturn( [] );
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
				function () {
				}
			],
			'Uploader, Expect input form' => [
				new FauxRequest(),
				true,
				null,
				function ( $html ) {
					$this->assertLandingPagePreset( $html );
				}
			],
			'Bad domain (not in allowed sites)' => [
				new FauxRequest( [
					'clientUrl' => 'https://test.wikimedia.org/wiki/File:AnyFile.JPG'
				] ),
				true,
				null,
				function ( $html ) {
					$this->assertWarningBox( $html, 'Can\'t import the given URL' );
				},
				[ 'FileImporter-WikimediaSitesTableSite' ]
			],
			'Bad domain (malformed?)' => [
				new FauxRequest( [
					'clientUrl' => 't243ju89gujwe9fjka09jg'
				] ),
				true,
				null,
				function ( $html ) {
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
					$this->assertWarningBox(
						$html,
						'File not found: https://commons.wikimedia.org/wiki/ThisIsNotAFileFooBarBarBar'
					);
				},
				[],
				true
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
						'Chicken In Snow.JPG'
					);
				},
				[],
				true
			],
			'Good file & Good target title' => [
				new FauxRequest( [
					'clientUrl' => 'https://commons.wikimedia.org/wiki/File:Chicken_In_Snow.JPG',
					'intendedFileName' => 'Chicken In Snow CHANGED',
					// XXX: This is currently not checked?
					'importDetailsHash' => 'SomeHash',
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
						'Chicken In Snow CHANGED.JPG'
					);
				},
				[],
				true
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

	private function assertLandingPagePreset( $html ) {
		assertThat(
			$html,
			is( htmlPiece( havingChild( withTagName( 'p' ) ) ) )
		);
	}

	private function assertWarningBox( $html, $text ) {
		assertThat(
			$html,
			is( htmlPiece( havingChild(
				both( withTagName( 'div' ) )
					->andAlso( withClass( 'errorbox' ) )
					->andAlso( havingChild(
						both( withTagName( 'p' ) )
							->andAlso( havingTextContents( startsWith( $text ) ) )
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
	 * @dataProvider provideTestData
	 */
	public function testSpecialPageExecutionWithVariousInputs(
		WebRequest $request,
		$userOrBool,
		$expectedExceptionDetails = null,
		$htmlAssertionCallable,
		array $sourceSiteServicesOverride = [],
		$requiresAccessToCommons = false
	) {
		if ( $requiresAccessToCommons && !self::$hasAccessToCommons ) {
			$this->markTestSkipped( 'This test requires http access to https://commons.wikimedia.org' );
		}

		if ( !empty( $sourceSiteServicesOverride ) ) {
			$this->setMwGlobals(
				'wgFileImporterSourceSiteServices',
				$sourceSiteServicesOverride
			);
		}

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

		list( $html, ) = $this->executeSpecialPage(
			'',
			$request,
			'en',
			$user
		);

		$htmlAssertionCallable( $html );
		// assertion to avoid phpunit showing hamcrest test as risky
		$this->addToAssertionCount( 1 );
	}

}
