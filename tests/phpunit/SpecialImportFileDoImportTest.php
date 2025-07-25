<?php

namespace FileImporter\Tests;

use FileImporter\Data\ImportDetails;
use FileImporter\Data\ImportPlan;
use FileImporter\Data\ImportRequest;
use FileImporter\Exceptions\LocalizedImportException;
use FileImporter\Remote\MediaWiki\RemoteSourceFileEditDeleteAction;
use FileImporter\Services\Importer;
use FileImporter\Services\SourceSite;
use FileImporter\Services\SourceSiteLocator;
use FileImporter\SpecialImportFile;
use MediaWiki\Language\Language;
use MediaWiki\Language\RawMessage;
use MediaWiki\Output\OutputPage;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Request\WebRequest;
use MediaWiki\Session\CsrfTokenSet;
use MediaWiki\Session\CsrfTokenSetProvider;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use MediaWikiIntegrationTestCase;
use Wikimedia\Stats\StatsFactory;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \FileImporter\SpecialImportFile
 *
 * @license GPL-2.0-or-later
 * @author Christoph Jauera <christoph.jauera@wikimedia.de>
 */
class SpecialImportFileDoImportTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		OutputPage::setupOOUI();
	}

	/**
	 * @return SpecialImportFile
	 */
	protected function newSpecialPage( WebRequest $fauxRequest, bool $tokenMatches, bool $importerResult ) {
		$outPageMock = $this->createMock( OutputPage::class );
		$outPageMock->method( 'getRequest' )
			->willReturn( $fauxRequest );

		$importerMock = $this->createMock( Importer::class );
		if ( $importerResult === false ) {
			$importerMock->method( 'import' )
				->willThrowException( new LocalizedImportException( 'test-error' ) );
		}

		$postImportHandler = $this->createMock( RemoteSourceFileEditDeleteAction::class );
		$postImportHandler->method( 'execute' )
			->willReturn( \StatusValue::newGood() );

		$sourceSiteMock = $this->createMock( SourceSite::class );
		$sourceSiteMock->method( 'getPostImportHandler' )
			->willReturn( $postImportHandler );

		$sourceSiteLocatorMock = $this->createMock( SourceSiteLocator::class );
		$sourceSiteLocatorMock->method( 'getSourceSite' )
			->willReturn( $sourceSiteMock );

		$specialImportFileMock = $this->getMockBuilder( SpecialImportFile::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'getContext', 'getOutput', 'getUser', 'getLanguage', 'msg' ] )
			->getMock();
		$specialImportFileMock->method( 'getContext' )
			->willReturn( $this->createTokenProvider( $tokenMatches ) );
		$specialImportFileMock->method( 'getOutput' )
			->willReturn( $outPageMock );
		$specialImportFileMock->method( 'getUser' )
			->willReturn( $this->createMock( User::class ) );
		$language = $this->createMock( Language::class );
		$language->method( 'getCode' )->willReturn( 'qqx' );
		$specialImportFileMock->method( 'getLanguage' )
			->willReturn( $language );
		$specialImportFileMock->method( 'msg' )
			->willReturn( new RawMessage( '' ) );

		/** @var SpecialImportFile $specialImportFileMock */
		$specialImportFileMock = TestingAccessWrapper::newFromObject( $specialImportFileMock );
		$specialImportFileMock->importer = $importerMock;
		$specialImportFileMock->sourceSiteLocator = $sourceSiteLocatorMock;
		$specialImportFileMock->statsFactory = StatsFactory::newNull();

		return $specialImportFileMock;
	}

	public static function provideSpecialPageDoImportTest() {
		return [
			'wrong edit token will return false' =>
			[
				'hash', [ 'importDetailsHash' => 'hash' ], false, true, false
			],
			'wrong import hash will return false' =>
			[
				'hash', [ 'importDetailsHash' => 'clash' ], true, true, false
			],
			'failed import attempt will return false' =>
			[
				'hash', [ 'importDetailsHash' => 'hash' ], true, false, false
			],
			'everything fine will return true' =>
			[
				'hash', [ 'importDetailsHash' => 'hash' ], true, true, true
			],
		];
	}

	/**
	 * @dataProvider provideSpecialPageDoImportTest
	 */
	public function testSpecialPageDoImportTest(
		string $origHash,
		array $requestData,
		bool $tokenMatches,
		bool $importerResult,
		bool $expected
	) {
		$importPlanMock = $this->createMockImportPlan( $origHash );

		$specialImportFile = $this->newSpecialPage(
			new FauxRequest( $requestData ),
			$tokenMatches,
			$importerResult
		);
		$this->assertSame( $expected, $specialImportFile->doImport( $importPlanMock ) );
	}

	private function createMockImportPlan( string $origHash ): ImportPlan {
		$importDetailsMock = $this->createMock( ImportDetails::class );
		$importDetailsMock->method( 'getOriginalHash' )
			->willReturn( $origHash );

		$importPlanMock = $this->createMock( ImportPlan::class );
		$importPlanMock->method( 'getDetails' )
			->willReturn( $importDetailsMock );
		$importPlanMock->method( 'getRequest' )
			->willReturn( new ImportRequest( '//example.com' ) );
		$importPlanMock->method( 'getTitle' )
			->willReturn( Title::makeTitle( NS_MAIN, __METHOD__ ) );
		$importPlanMock->method( 'getActionStats' )
			->willReturn( [] );
		$importPlanMock->method( 'getValidationWarnings' )
			->willReturn( [] );

		return $importPlanMock;
	}

	private function createTokenProvider( bool $tokenMatches ): CsrfTokenSetProvider {
		$token = $this->createMock( CsrfTokenSet::class );
		$token->method( 'matchToken' )
			->willReturn( $tokenMatches );

		$tokenProvider = $this->createMock( CsrfTokenSetProvider::class );
		$tokenProvider->method( 'getCsrfTokenSet' )
			->willReturn( $token );
		return $tokenProvider;
	}

}
