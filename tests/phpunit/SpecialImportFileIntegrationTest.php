<?php

namespace FileImporter\Tests;

use FauxRequest;
use FileImporter\Exceptions\HttpRequestException;
use FileImporter\Remote\MediaWiki\SiteTableSiteLookup;
use FileImporter\Services\Http\HttpRequestExecutor;
use FileImporter\SpecialImportFile;
use HamcrestPHPUnitIntegration;
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
	use HamcrestPHPUnitIntegration;

	protected function setUp(): void {
		parent::setUp();

		$this->setMwGlobals( [
			'wgEnableUploads' => true,
			'wgFileImporterShowInputScreen' => true,
			'wgFileImporterCommonsHelperServer' => '',
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
	private function getMockSite( $globalId, $domain ): Site {
		$mockSite = $this->createMock( Site::class );
		$mockSite->method( 'getGlobalId' )
			->willReturn( $globalId );
		$mockSite->method( 'getDomain' )
			->willReturn( $domain );
		$mockSite->method( 'getNavigationIds' )
			->willReturn( [] );
		return $mockSite;
	}

	protected function newSpecialPage(): SpecialPage {
		$services = $this->getServiceContainer();
		return new SpecialImportFile(
			$services->getService( 'FileImporterSourceSiteLocator' ),
			$services->getService( 'FileImporterImporter' ),
			$services->getService( 'FileImporterImportPlanFactory' ),
			$services->getStatsdDataFactory(),
			$services->getUserOptionsManager(),
			$services->getMainConfig()
		);
	}

	private function getPageNotFoundHttpException(): HttpRequestException {
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
	private function getHttpRequestMock( $fileName ): MWHttpRequest {
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
				static function () {
				}
			],
			'Uploader, Expect input form' => [
				new FauxRequest(),
				true,
				null,
				function ( $html ) {
					$this->assertStringContainsString( " name='clientUrl'", $html );
				}
			],
			'Bad domain (not in allowed sites)' => [
				new FauxRequest( [
					'clientUrl' => 'https://test.wikimedia.org/wiki/File:AnyFile.JPG'
				] ),
				true,
				null,
				function ( $html ) {
					$this->assertErrorBox( $html, 'Can\'t import the given URL' );
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
					$this->assertErrorBox( $html, 'Can\'t parse the given URL: t243ju89gujwe9fjka09jg' );
				}
			],
			'Bad file' => [
				new FauxRequest( [
					'clientUrl' => 'https://commons.wikimedia.org/wiki/ThisIsNotAFileFooBarBarBar'
				] ),
				true,
				null,
				function ( $html ) {
					$this->assertErrorBox(
						$html,
						'File not found: https://commons.wikimedia.org/wiki/ThisIsNotAFileFooBarBarBar.'
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
					$this->assertStringContainsString(
						'<h2 class="mw-importfile-header-title">Chicken In Snow.JPG</h2>',
						$html
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
					$this->assertStringContainsString(
						'<h2 class="mw-importfile-header-title">Chicken In Snow CHANGED.JPG</h2>',
						$html
					);
				},
				[],
				$this->getSuccessfulHttpRequestExecutorCalls(),
			],
		];
	}

	private function assertErrorBox( $html, $text ) {
		$this->assertStringContainsString( 'mw-importfile-error-banner', $html );
		$this->assertStringContainsString( 'mw-message-box-error', $html );
		$this->assertStringContainsString( htmlspecialchars( $text, ENT_NOQUOTES ), $html );
	}

	private function assertPreviewPage( $html, $clientUrl, $intendedFileName ) {
		$this->assertThatHamcrest(
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
		?array $expectedExceptionDetails,
		callable $htmlAssertionCallable,
		array $sourceSiteServicesOverride = [],
		array $httpRequestMockResponses = []
	) {
		$httpRequestExecutorMock = $this->createMock( HttpRequestExecutor::class );
		$httpRequestExecutorMock->expects( $this->atMost( 3 ) )
			->method( 'execute' )
			->willReturnOnConsecutiveCalls( ...$httpRequestMockResponses );
		$this->setService( 'FileImporterHttpRequestExecutor', $httpRequestExecutorMock );

		if ( $sourceSiteServicesOverride !== [] ) {
			$this->setMwGlobals( 'wgFileImporterSourceSiteServices', $sourceSiteServicesOverride );
		}

		if ( $expectedExceptionDetails ) {
			$this->expectException( $expectedExceptionDetails['name'] );
			$this->expectExceptionMessage( $expectedExceptionDetails['message'] );
		}

		if ( $userOrBool instanceof User ) {
			$user = $userOrBool;
		} elseif ( $userOrBool ) {
			$user = $this->getTestSysop()->getUser();
		} else {
			$user = $this->getTestUser()->getUser();
		}

		[ $html, ] = $this->executeSpecialPage(
			'',
			$request,
			'en',
			$user
		);

		$htmlAssertionCallable( $html );
	}

}
