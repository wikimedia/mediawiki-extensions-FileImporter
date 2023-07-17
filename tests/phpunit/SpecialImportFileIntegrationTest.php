<?php

namespace FileImporter\Tests;

use FileImporter\Exceptions\HttpRequestException;
use FileImporter\Remote\MediaWiki\SiteTableSiteLookup;
use FileImporter\Services\Http\HttpRequestExecutor;
use FileImporter\SpecialImportFile;
use Hamcrest\Matcher;
use HamcrestPHPUnitIntegration;
use HashSiteStore;
use Liuggio\StatsdClient\Factory\StatsdDataFactoryInterface;
use MediaWiki\Content\IContentHandlerFactory;
use MediaWiki\Request\FauxRequest;
use MediaWiki\User\UserOptionsManager;
use MWHttpRequest;
use PermissionsError;
use Site;
use SpecialPage;
use SpecialPageTestBase;
use StatusValue;
use User;

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
	private function getMockSite( string $globalId, string $domain ): Site {
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
			$this->createMock( IContentHandlerFactory::class ),
			$this->createMock( StatsdDataFactoryInterface::class ),
			$this->createMock( UserOptionsManager::class ),
			$services->getMainConfig()
		);
	}

	private function newHttpRequestException( int $statusCode ): HttpRequestException {
		$httpRequestMock = $this->createMock( MWHttpRequest::class );
		$httpRequestMock->method( 'getStatus' )->willReturn( $statusCode );
		return new HttpRequestException( StatusValue::newGood(), $httpRequestMock );
	}

	/**
	 * @param string $fileName
	 *
	 * @return MWHttpRequest
	 */
	private function getHttpRequestMock( string $fileName ): MWHttpRequest {
		$httpRequestMock = $this->createMock( MWHttpRequest::class );
		$httpRequestMock->method( 'getContent' )->willReturn(
			file_get_contents( $fileName )
		);
		return $httpRequestMock;
	}

	public function provideTestData() {
		$successfulHttpResponses = [
			// HttpApiLookup::actuallyGetApiUrl
			__DIR__ . '/res/IntegrationTests/apiLookup.html',
			// ApiDetailRetriever::sendApiRequest
			__DIR__ . '/res/IntegrationTests/detailRetriever.json',
			// RemoteApiImportTitleChecker::importAllowed
			__DIR__ . '/res/IntegrationTests/titleChecker.json',
		];

		return [
			'Anon user, Expect Groups required' => [
				'webRequest' => [],
				'user' => new User(),
				'expectedException' => [
					'class' => PermissionsError::class,
					'message' => 'The action you have requested is limited to users in one of the groups',
				],
				'htmlAssertions' => static function () {
				}
			],
			'Uploader, Expect input form' => [
				'webRequest' => [],
				'user' => true,
				'expectedException' => null,
				'htmlAssertions' => function ( string $html ): void {
					$this->assertStringContainsString( " name='clientUrl'", $html );
				}
			],
			'Bad domain (not in allowed sites)' => [
				'webRequest' => [
					'clientUrl' => 'https://test.wikimedia.org/wiki/File:AnyFile.JPG'
				],
				'user' => true,
				'expectedException' => null,
				'htmlAssertions' => function ( string $html ): void {
					$this->assertErrorBox( $html, 'Can\'t import the given URL' );
				},
				'sourceSiteServices' => [ 'FileImporter-WikimediaSitesTableSite' ],
			],
			'Bad domain (malformed?)' => [
				'webRequest' => [
					'clientUrl' => 't243ju89gujwe9fjka09jg'
				],
				'user' => true,
				'expectedException' => null,
				'htmlAssertions' => function ( string $html ): void {
					$this->assertErrorBox( $html, 'Can\'t parse the given URL: t243ju89gujwe9fjka09jg' );
				}
			],
			'Bad file' => [
				'webRequest' => [
					'clientUrl' => 'https://commons.wikimedia.org/wiki/ThisIsNotAFileFooBarBarBar'
				],
				'user' => true,
				'expectedException' => null,
				'htmlAssertions' => function ( string $html ): void {
					$this->assertErrorBox(
						$html,
						'File not found: https://commons.wikimedia.org/wiki/ThisIsNotAFileFooBarBarBar.'
					);
				},
				'sourceSiteServices' => [],
				'httpResponses' => [ 404 ],
			],
			'Good file' => [
				'webRequest' => [
					'clientUrl' => 'https://commons.wikimedia.org/wiki/File:Chicken_In_Snow.JPG',
				],
				'user' => true,
				'expectedException' => null,
				'htmlAssertions' => function ( string $html ): void {
					$this->assertPreviewPage(
						$html,
						'https://commons.wikimedia.org/wiki/File:Chicken_In_Snow.JPG',
						'Chicken In Snow'
					);
					$this->assertStringContainsString(
						'<h2>Chicken In Snow.JPG</h2>',
						$html
					);
				},
				'sourceSiteServices' => [],
				'httpResponses' => $successfulHttpResponses,
			],
			'Good file & Good target title' => [
				'webRequest' => [
					'clientUrl' => 'https://commons.wikimedia.org/wiki/File:Chicken_In_Snow.JPG',
					'intendedFileName' => 'Chicken In Snow CHANGED',
					// XXX: This is currently not checked?
					'importDetailsHash' => 'SomeHash',
				],
				'user' => true,
				'expectedException' => null,
				'htmlAssertions' => function ( string $html ): void {
					$this->assertPreviewPage(
						$html,
						'https://commons.wikimedia.org/wiki/File:Chicken_In_Snow.JPG',
						'Chicken In Snow CHANGED'
					);
					$this->assertStringContainsString(
						'<h2>Chicken In Snow CHANGED.JPG</h2>',
						$html
					);
				},
				'sourceSiteServices' => [],
				'httpResponses' => $successfulHttpResponses,
			],
		];
	}

	private function assertErrorBox( string $html, string $text ): void {
		$this->assertStringContainsString( 'mw-importfile-error-banner', $html );
		$this->assertStringContainsString( 'mw-message-box-error', $html );
		$this->assertStringContainsString( htmlspecialchars( $text, ENT_NOQUOTES ), $html );
	}

	private function assertPreviewPage( string $html, string $clientUrl, string $intendedFileName ): void {
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

	private function thatIsHiddenInputField( string $name, string $value ): Matcher {
		return both( withTagName( 'input' ) )
			->andAlso( withAttribute( 'type' )->havingValue( 'hidden' ) )
			->andAlso( withAttribute( 'name' )->havingValue( $name ) )
			->andAlso( withAttribute( 'value' )->havingValue( $value ) );
	}

	private function thatIsHiddenInputFieldWithSomeValue( string $name ): Matcher {
		return both( withTagName( 'input' ) )
			->andAlso( withAttribute( 'type' )->havingValue( 'hidden' ) )
			->andAlso( withAttribute( 'name' )->havingValue( $name ) )
			->andAlso( withAttribute( 'value' ) );
	}

	/**
	 * @dataProvider provideTestData
	 */
	public function testSpecialPageExecutionWithVariousInputs(
		array $webRequest,
		$userOrBool,
		?array $expectedExceptionDetails,
		callable $htmlAssertionCallable,
		array $sourceSiteServicesOverride = [],
		array $httpRequestMockResponses = []
	) {
		$httpRequestExecutorMock = $this->createMock( HttpRequestExecutor::class );
		$httpRequestExecutorMock->expects( $this->atMost( 3 ) )
			->method( 'execute' )
			->willReturnOnConsecutiveCalls( ...array_map( function ( $response ) {
				if ( is_int( $response ) ) {
					return $this->throwException( $this->newHttpRequestException( $response ) );
				}
				return $this->getHttpRequestMock( $response );
			}, $httpRequestMockResponses ) );
		$this->setService( 'FileImporterHttpRequestExecutor', $httpRequestExecutorMock );

		if ( $sourceSiteServicesOverride ) {
			$this->setMwGlobals( 'wgFileImporterSourceSiteServices', $sourceSiteServicesOverride );
		}

		if ( $expectedExceptionDetails ) {
			$this->expectException( $expectedExceptionDetails['class'] );
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
			new FauxRequest( $webRequest ),
			'en',
			$user
		);

		$htmlAssertionCallable( $html );
	}

}
