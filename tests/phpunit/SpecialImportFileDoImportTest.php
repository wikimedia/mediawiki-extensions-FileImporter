<?php

namespace FileImporter\Test;

use FauxRequest;
use FileImporter\Data\ImportDetails;
use FileImporter\Data\ImportPlan;
use FileImporter\Data\ImportRequest;
use FileImporter\Services\Importer;
use FileImporter\SpecialImportFile;
use OOUI\ApexTheme;
use OOUI\Theme;
use OutputPage;
use Title;
use User;
use WebRequest;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \FileImporter\SpecialImportFile

 * @license GPL-2.0-or-later
 * @author Christoph Jauera <christoph.jauera@wikimedia.de>
 */
class SpecialImportFileDoImportTest extends \MediaWikiTestCase {

	public function setUp() {
		parent::setUp();
		Theme::setSingleton( new ApexTheme() );
	}

	public function tearDown() {
		Theme::setSingleton( null );
		parent::tearDown();
	}

	/**
	 * @param WebRequest $fauxRequest
	 * @param User $user
	 * @param $importerResult
	 * @return SpecialImportFile
	 */
	protected function newSpecialPage( WebRequest $fauxRequest, User $user, $importerResult ) {
		$outPageMock = $this->createMock( OutputPage::class );
		$outPageMock->method( 'getRequest' )
			->willReturn( $fauxRequest );

		$importerMock = $this->createMock( Importer::class );
		$importerMock->method( 'import' )
			->willReturn( $importerResult );

		$specialImportFileMock = $this->getMockBuilder( SpecialImportFile::class )
			->setMethods( [ 'getOutput', 'getUser' ] )
			->getMock();
		$specialImportFileMock->method( 'getOutput' )
			->willReturn( $outPageMock );
		$specialImportFileMock->method( 'getUser' )
			->willReturn( $user );

		$specialImportFileMock = TestingAccessWrapper::newFromObject( $specialImportFileMock );
		$specialImportFileMock->importer = $importerMock;

		return $specialImportFileMock;
	}

	public function provideSpecialPageDoImportTest() {
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
	public function testSpecialPageDoImportTest( $origHash, $requestData, $tokenCheck,
												 $importerResult, $expected ) {
		$importPlanMock = $this->createMockImportPlan( $origHash );

		$specialImportFile = $this->newSpecialPage(
			$this->createFauxRequest( $requestData ),
			$this->createMockUser( $tokenCheck ),
			$importerResult
		);
		$this->assertSame( $expected, $specialImportFile->doImport( $importPlanMock ) );
	}

	/**
	 * @param string $origHash
	 * @return ImportPlan
	 */
	private function createMockImportPlan( $origHash ) {
		$importDetailsMock = $this->createMock( ImportDetails::class );
		$importDetailsMock->method( 'getOriginalHash' )
			->willReturn( $origHash );

		$importPlanMock = $this->createMock( ImportPlan::class );
		$importPlanMock->method( 'getDetails' )
			->willReturn( $importDetailsMock );
		$importPlanMock->method( 'getRequest' )
			->willReturn( new ImportRequest( 'http://example.com' ) );
		$importPlanMock->method( 'getTitle' )
			->willReturn( Title::newFromText( 'Test' ) );

		return $importPlanMock;
	}

	/**
	 * @param array $parameters
	 * @return WebRequest
	 */
	private function createFauxRequest( array $parameters ) {
		return new FauxRequest( $parameters );
	}

	/**
	 * @param bool $tokenMatches
	 * @return User
	 */
	private function createMockUser( $tokenMatches = false ) {
		$mockUser = $this->createMock( User::class );
		$mockUser->method( 'matchEditToken' )
			->willReturn( $tokenMatches );

		return $mockUser;
	}

}
