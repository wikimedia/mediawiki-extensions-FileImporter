<?php

namespace FileImporter\Tests\Remote\MediaWiki;

use Exception;
use FileImporter\Data\SourceUrl;
use FileImporter\Remote\MediaWiki\CentralAuthTokenProvider;
use FileImporter\Remote\MediaWiki\HttpApiLookup;
use FileImporter\Remote\MediaWiki\RemoteApiRequestExecutor;
use FileImporter\Services\Http\HttpRequestExecutor;
use MediaWiki\User\CentralId\CentralIdLookup;
use MediaWiki\User\User;
use MediaWikiUnitTestCase;
use MWHttpRequest;

/**
 * @covers \FileImporter\Remote\MediaWiki\RemoteApiRequestExecutor
 *
 * @license GPL-2.0-or-later
 */
class RemoteApiRequestExecutorTest extends MediaWikiUnitTestCase {

	public function testGetCsrfToken_success() {
		$centralAuthToken = 'abc' . mt_rand();
		$csrfToken = 'abc' . mt_rand();

		$mockResponse = $this->createMock( MWHttpRequest::class );
		$mockResponse
			->method( 'getContent' )
			->willReturn( json_encode(
				[ 'query' => [ 'tokens' => [ 'csrftoken' => $csrfToken ] ] ]
			) );
		$mockHttpRequestExecutor = $this->createMock( HttpRequestExecutor::class );
		$mockHttpRequestExecutor
			->expects( $this->once() )
			->method( 'execute' )
			->with( "//w.invalid/w/api.php?centralauthtoken=$centralAuthToken", [
				'action' => 'query',
				'meta' => 'tokens',
				'type' => 'csrf',
				'format' => 'json',
				'formatversion' => 2,
			] )
			->willReturn( $mockResponse );

		$apiRequestExecutor = new RemoteApiRequestExecutor(
			$this->createHttpApiLookup(),
			$mockHttpRequestExecutor,
			$this->createCentralAuthTokenProvider( $centralAuthToken ),
			$this->createCentralIdLookup()
		);

		$this->assertEquals(
			$csrfToken,
			$apiRequestExecutor->getCsrfToken(
				$this->createMock( SourceUrl::class ),
				$this->createNoOpMock( User::class ) )
		);
	}

	public function testGetCsrfToken_failedCentralAuthToken() {
		$mockHttpRequestExecutor = $this->createMock( HttpRequestExecutor::class );
		$mockHttpRequestExecutor
			->expects( $this->never() )
			->method( 'execute' );
		$mockCentralAuthTokenProvider = $this->createMock( CentralAuthTokenProvider::class );
		$mockCentralAuthTokenProvider
			->method( 'getToken' )
			->willThrowException( new Exception() );

		$apiRequestExecutor = new RemoteApiRequestExecutor(
			$this->createHttpApiLookup(),
			$mockHttpRequestExecutor,
			$mockCentralAuthTokenProvider,
			$this->createCentralIdLookup()
		);

		$this->assertNull(
			$apiRequestExecutor->getCsrfToken(
				$this->createMock( SourceUrl::class ),
				$this->createNoOpMock( User::class ) )
		);
	}

	public function testGetCsrfToken_failedCsrfToken() {
		$centralAuthToken = 'abc' . mt_rand();

		$mockResponse = $this->createMock( MWHttpRequest::class );
		$mockResponse
			->method( 'getContent' )
			->willReturn( json_encode( [
				'query' => [
					'tokens' => []
				]
			] ) );
		$mockHttpRequestExecutor = $this->createMock( HttpRequestExecutor::class );
		$mockHttpRequestExecutor
			->expects( $this->once() )
			->method( 'execute' )
			->with( "//w.invalid/w/api.php?centralauthtoken=$centralAuthToken", [
				'action' => 'query',
				'meta' => 'tokens',
				'type' => 'csrf',
				'format' => 'json',
				'formatversion' => 2,
			] )
			->willReturn( $mockResponse );

		$apiRequestExecutor = new RemoteApiRequestExecutor(
			$this->createHttpApiLookup(),
			$mockHttpRequestExecutor,
			$this->createCentralAuthTokenProvider( $centralAuthToken ),
			$this->createCentralIdLookup()
		);

		$this->assertNull(
			$apiRequestExecutor->getCsrfToken(
				$this->createMock( SourceUrl::class ),
				$this->createNoOpMock( User::class ) )
		);
	}

	public function testExecute_successPost() {
		$queryParams = [ 'a' => 'foo' ];
		$expectedResult = [ 'foo' => 'bar' ];

		$centralAuthToken = 'abc' . mt_rand();
		$csrfToken = 'abc' . mt_rand();

		$mockResponse = $this->createMock( MWHttpRequest::class );
		$mockResponse
			->method( 'getContent' )
			->willReturn( json_encode(
				[ 'query' => [ 'tokens' => [ 'csrftoken' => $csrfToken ] ] ]
			) );
		$mockPostResponse = $this->createMock( MWHttpRequest::class );
		$mockPostResponse
			->method( 'getContent' )
			->willReturn( json_encode( $expectedResult ) );
		$mockHttpRequestExecutor = $this->createMock( HttpRequestExecutor::class );
		$mockHttpRequestExecutor
			->method( 'execute' )
			->willReturn( $mockResponse );
		$mockHttpRequestExecutor
			->method( 'executePost' )
			->with( "//w.invalid/w/api.php?centralauthtoken=$centralAuthToken", $queryParams )
			->willReturn( $mockPostResponse );
		$mockUser = $this->createMock( User::class );
		$mockUser->method( 'isSafeToLoad' )
			->willReturn( true );

		$apiRequestExecutor = new RemoteApiRequestExecutor(
			$this->createHttpApiLookup(),
			$mockHttpRequestExecutor,
			$this->createCentralAuthTokenProvider( $centralAuthToken ),
			$this->createCentralIdLookup()
		);

		$this->assertEquals(
			$expectedResult,
			$apiRequestExecutor->execute(
				$this->createMock( SourceUrl::class ),
				$mockUser,
				$queryParams,
				true
			)
		);
	}

	private function createHttpApiLookup(): HttpApiLookup {
		$mockHttpApiLookup = $this->createMock( HttpApiLookup::class );
		$mockHttpApiLookup
			->method( 'getApiUrl' )
			->willReturn( '//w.invalid/w/api.php' );
		return $mockHttpApiLookup;
	}

	/**
	 * @param string $centralAuthToken
	 */
	private function createCentralAuthTokenProvider(
		$centralAuthToken
	): CentralAuthTokenProvider {
		$mockCentralAuthTokenProvider = $this->createMock( CentralAuthTokenProvider::class );
		$mockCentralAuthTokenProvider
			->method( 'getToken' )
			->willReturn( $centralAuthToken );
		return $mockCentralAuthTokenProvider;
	}

	private function createCentralIdLookup(): CentralIdLookup {
		$mockCentralIdLookup = $this->createMock( CentralIdLookup::class );
		$mockCentralIdLookup
			->method( 'centralIdFromLocalUser' )
			->willReturn( 1 );
		return $mockCentralIdLookup;
	}

}
