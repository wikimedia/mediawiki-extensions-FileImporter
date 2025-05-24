<?php

namespace FileImporter\Tests;

use FileImporter\Exceptions\HttpRequestException;
use FileImporter\Remote\MediaWiki\SiteTableSiteLookup;
use FileImporter\Services\Http\HttpRequestExecutor;
use FileImporter\SpecialImportFile;
use Hamcrest\Matcher;
use MediaWiki\Config\HashConfig;
use MediaWiki\Content\IContentHandlerFactory;
use MediaWiki\Exception\PermissionsError;
use MediaWiki\MainConfigNames;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Site\HashSiteStore;
use MediaWiki\Site\Site;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\User\Options\UserOptionsManager;
use MediaWiki\User\User;
use MWHttpRequest;
use SpecialPageTestBase;
use StatusValue;
use Wikimedia\Stats\StatsFactory;

/**
 * @coversNothing
 * @group Database
 *
 * TODO: Rename to make it clear that we're only testing preview, not submit.
 * TODO: Remove from Database group once it's possible to use Authority for permission checks.
 *
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class SpecialImportFileIntegrationTest extends SpecialPageTestBase {

	protected function setUp(): void {
		parent::setUp();

		$this->setUserLang( 'qqx' );
		$this->overrideConfigValues( [
			MainConfigNames::EnableUploads => true,
			'FileImporterShowInputScreen' => true,
			'FileImporterCommonsHelperServer' => '',
			'FileImporterSourceWikiDeletion' => false,
			'FileImporterSourceWikiTemplating' => false,
		] );

		$commonsSite = $this->getMockSite( 'commonswiki', 'commons.wikimedia.org' );
		$hashSiteStore = new HashSiteStore( [ $commonsSite ] );
		$siteTableSiteLookup = new SiteTableSiteLookup( $hashSiteStore );
		$this->setService( 'FileImporterMediaWikiSiteTableSiteLookup', $siteTableSiteLookup );
	}

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
			$services->getService( 'FileImporterMediaWikiRemoteApiActionExecutor' ),
			$services->getService( 'FileImporterTemplateLookup' ),
			$this->createNoOpMock( IContentHandlerFactory::class ),
			StatsFactory::newNull(),
			$this->createNoOpMock( UserOptionsManager::class ),
			new HashConfig( [
				'FileImporterRequiredRight' => '',
				'FileImporterShowInputScreen' => true,
			] )
		);
	}

	private function newHttpRequestException( int $statusCode ): HttpRequestException {
		$httpRequestMock = $this->createMock( MWHttpRequest::class );
		$httpRequestMock->method( 'getStatus' )->willReturn( $statusCode );
		return new HttpRequestException( StatusValue::newGood(), $httpRequestMock );
	}

	private function getHttpRequestMock( string $fileName ): MWHttpRequest {
		$httpRequestMock = $this->createMock( MWHttpRequest::class );
		$httpRequestMock->method( 'getContent' )->willReturn(
			file_get_contents( $fileName )
		);
		return $httpRequestMock;
	}

	public static function provideTestData() {
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
				'htmlAssertions' => static function (): void {
				}
			],
			'Uploader, Expect input form' => [
				'webRequest' => [],
				'user' => true,
				'expectedException' => null,
				'htmlAssertions' => function ( string $html ): void {
					self::assertStringContainsString( " name='clientUrl'", $html );
				}
			],
			'Bad domain (not in allowed sites)' => [
				'webRequest' => [
					'clientUrl' => 'https://test.wikimedia.org/wiki/File:AnyFile.JPG'
				],
				'user' => true,
				'expectedException' => null,
				'htmlAssertions' => function ( string $html ): void {
					self::assertErrorBox( $html, '(fileimporter-cantimporturl)' );
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
					self::assertErrorBox( $html, '(fileimporter-cantparseurl: t243ju89gujwe9fjka09jg)' );
				}
			],
			'Bad file' => [
				'webRequest' => [
					'clientUrl' => 'https://commons.wikimedia.org/wiki/NotAFile'
				],
				'user' => true,
				'expectedException' => null,
				'htmlAssertions' => function ( string $html ): void {
					self::assertErrorBox(
						$html,
						'(fileimporter-api-file-notfound: https://commons.wikimedia.org/wiki/NotAFile)'
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
					self::assertPreviewPage(
						$html,
						'https://commons.wikimedia.org/wiki/File:Chicken_In_Snow.JPG',
						'Chicken In Snow'
					);
					self::assertStringContainsString(
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
					self::assertPreviewPage(
						$html,
						'https://commons.wikimedia.org/wiki/File:Chicken_In_Snow.JPG',
						'Chicken In Snow CHANGED'
					);
					self::assertStringContainsString(
						'<h2>Chicken In Snow CHANGED.JPG</h2>',
						$html
					);
				},
				'sourceSiteServices' => [],
				'httpResponses' => $successfulHttpResponses,
			],
		];
	}

	private static function assertErrorBox( string $html, string $text ): void {
		self::assertStringContainsString( 'mw-importfile-error-banner', $html );
		self::assertStringContainsString( 'cdx-message--error', $html );
		self::assertStringContainsString( htmlspecialchars( $text, ENT_NOQUOTES ), $html );
	}

	private static function assertPreviewPage( string $html, string $clientUrl, string $intendedFileName ): void {
		assertThat(
			$html,
			is( htmlPiece( havingChild(
				both( withTagName( 'form' ) )
					->andAlso( withAttribute( 'action' ) )
					->andAlso( withAttribute( 'method' )->havingValue( 'POST' ) )
					->andAlso( havingChild( self::thatIsHiddenInputField( 'clientUrl', $clientUrl ) ) )
					->andAlso(
						havingChild( self::thatIsHiddenInputField( 'intendedFileName', $intendedFileName ) )
					)
					->andAlso( havingChild( self::thatIsHiddenInputField( 'importDetailsHash', anything() ) ) )
					->andAlso( havingChild( self::thatIsHiddenInputField( 'token', anything() ) ) )
					->andAlso( havingChild(
						both( withTagName( 'button' ) )
							->andAlso( withAttribute( 'type' )->havingValue( 'submit' ) )
							->andAlso( withAttribute( 'name' )->havingValue( 'action' ) )
							->andAlso( withAttribute( 'value' )->havingValue( 'submit' ) )
					) )
			) ) )
		);
	}

	private static function thatIsHiddenInputField( string $name, $value ): Matcher {
		return both( withTagName( 'input' ) )
			->andAlso( withAttribute( 'type' )->havingValue( 'hidden' ) )
			->andAlso( withAttribute( 'name' )->havingValue( $name ) )
			->andAlso( withAttribute( 'value' )->havingValue( $value ) );
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
			$this->overrideConfigValue( 'FileImporterSourceSiteServices', $sourceSiteServicesOverride );
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
			'qqx',
			$user
		);

		$htmlAssertionCallable( $html );
	}

}
