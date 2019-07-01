<?php

namespace FileImporter\Tests;

use FauxRequest;
use FileImporter\Exceptions\HttpRequestException;
use FileImporter\Remote\MediaWiki\SiteTableSiteLookup;
use FileImporter\Services\Http\HttpRequestExecutor;
use FileImporter\SpecialImportFile;
use HashSiteStore;
use MWHttpRequest;
use PermissionsError;
use Site;
use SpecialPage;
use SpecialPageTestBase;
use StatusValue;
use User;
use WebRequest;

/**
 * @coversNothing
 *
 * TODO: Rename to make it clear that we're only testing preview, not submit.
 *
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class SpecialImportFileIntegrationTest extends SpecialPageTestBase {

	public function setUp() {
		parent::setUp();

		$this->setMwGlobals( [
			'wgEnableUploads' => true,
			'wgFileImporterShowInputScreen' => true,
			'wgFileImporterSourceWikiDeletion' => false,
			'wgFileImporterSourceWikiTemplating' => false,
		] );

		$commonsSite = $this->getMockSite( 'commonswiki', 'commons.wikimedia.org' );
		$hashSiteStore = new HashSiteStore( [ $commonsSite ] );
		$siteTableSiteLookup = new SiteTableSiteLookup( $hashSiteStore );
		$this->setService( 'FileImporterMediaWikiSiteTableSiteLookup', $siteTableSiteLookup );
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
	 * @return SpecialPage
	 */
	protected function newSpecialPage() {
		return new SpecialImportFile();
	}

	/**
	 * @return HttpRequestException
	 */
	private function getPageNotFoundHttpException() {
		$httpRequestMock = $this->createMock( MWHttpRequest::class );
		$httpRequestMock->method( 'getStatus' )->willReturn( 404 );

		$statusValueMock = $this->createMock( StatusValue::class );
		$statusValueMock->method( 'getErrors' )->willReturn( [] );

		return new HttpRequestException( $statusValueMock, $httpRequestMock );
	}

	/**
	 * @param string $fileName
	 *
	 * @return MWHttpRequest
	 */
	private function getHttpRequestMock( $fileName ) {
		$httpRequestMock = $this->createMock( MWHttpRequest::class );
		$httpRequestMock->method( 'getContent' )->willReturn(
			file_get_contents( __DIR__ . '/res/IntegrationTests/' . $fileName )
		);
		return $httpRequestMock;
	}

	/**
	 * @return MWHttpRequest[]
	 */
	private function getSuccessfulHttpRequestExecutorCalls() {
		return [
			// HttpApiLookup::actuallyGetApiUrl
			$this->getHttpRequestMock( 'apiLookup.html' ),
			// ApiDetailRetriever::sendApiRequest
			$this->getHttpRequestMock( 'detailRetriever.json' ),
			// RemoteApiImportTitleChecker::importAllowed
			$this->getHttpRequestMock( 'titleChecker.json' )
		];
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
				[ $this->throwException( $this->getPageNotFoundHttpException() ) ],
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
				$this->getSuccessfulHttpRequestExecutorCalls(),
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
				$this->getSuccessfulHttpRequestExecutorCalls(),
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
					->andAlso( havingChild(
						both( withTagName( 'button' ) )
							->andAlso( withAttribute( 'type' )->havingValue( 'submit' ) )
							->andAlso( withAttribute( 'name' )->havingValue( 'action' ) )
							->andAlso( withAttribute( 'value' )->havingValue( 'submit' ) )
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
		array $httpRequestMockResponses = []
	) {
		$httpRequestExecutorMock = $this->createMock( HttpRequestExecutor::class );
		$httpRequestExecutorMock->method( 'execute' )->will(
			$this->onConsecutiveCalls( ...$httpRequestMockResponses )
		);
		$this->setService( 'FileImporterHttpRequestExecutor', $httpRequestExecutorMock );

		if ( $sourceSiteServicesOverride !== [] ) {
			$this->setMwGlobals( 'wgFileImporterSourceSiteServices', $sourceSiteServicesOverride );
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
