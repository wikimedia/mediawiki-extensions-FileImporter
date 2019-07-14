<?php

namespace FileImporter\Tests\Remote\MediaWiki;

use CentralIdLookup;
use Exception;
use FileImporter\Data\SourceUrl;
use FileImporter\Remote\MediaWiki\CentralAuthTokenProvider;
use FileImporter\Remote\MediaWiki\HttpApiLookup;
use FileImporter\Remote\MediaWiki\RemoteApiRequestExecutor;
use FileImporter\Services\Http\HttpRequestExecutor;
use MediaWikiUnitTestCase;
use MWHttpRequest;
use User;

/**
 * @covers \FileImporter\Remote\MediaWiki\RemoteApiRequestExecutor
 */
class RemoteApiRequestExecutorTest extends MediaWikiUnitTestCase {

	public function testGetCsrfToken_success() {
		$centralAuthToken = 'abc' . mt_rand();
		$csrfToken = 'abc' . mt_rand();

		$mockResponse = $this->createMock( MWHttpRequest::class );
		$mockResponse
			->method( 'getContent' )
			->willReturn( json_encode( [
				'query' => [
					'tokens' => [
						'csrftoken' => $csrfToken
					]
				]
			] ) );
		$mockHttpRequestExecutor = $this->createMock( HttpRequestExecutor::class );
		$mockHttpRequestExecutor
			->expects( $this->once() )
			->method( 'execute' )
			->with(
				'https://w.invalid/w/api.php?action=query&meta=tokens&' .
					'format=json&centralauthtoken=' . $centralAuthToken
			)
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
				$this->createMock( User::class ) )
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
				$this->createMock( User::class ) )
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
			->with(
				'https://w.invalid/w/api.php?action=query&meta=tokens&' .
				'format=json&centralauthtoken=' . $centralAuthToken
			)
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
				$this->createMock( User::class ) )
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
			->willReturn( json_encode( [
				'query' => [
					'tokens' => [
						'csrftoken' => $csrfToken
					]
				]
			] ) );
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
			->with(
				$this->anything(),
				$queryParams
			)
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
				true )
		);
	}

	/**
	 * @return HttpApiLookup
	 */
	private function createHttpApiLookup() {
		$mockHttpApiLookup = $this->createMock( HttpApiLookup::class );
		$mockHttpApiLookup
			->method( 'getApiUrl' )
			->willReturn( 'https://w.invalid/w/api.php' );
		return $mockHttpApiLookup;
	}

	/**
	 * @param string $centralAuthToken
	 * @return CentralAuthTokenProvider
	 */
	private function createCentralAuthTokenProvider( $centralAuthToken ) {
		$mockCentralAuthTokenProvider = $this->createMock( CentralAuthTokenProvider::class );
		$mockCentralAuthTokenProvider
			->method( 'getToken' )
			->willReturn( $centralAuthToken );
		return $mockCentralAuthTokenProvider;
	}

	/**
	 * @return CentralIdLookup
	 */
	private function createCentralIdLookup() {
		$mockCentralIdLookup = $this->createMock( CentralIdLookup::class );
		$mockCentralIdLookup
			->method( 'centralIdFromLocalUser' )
			->willReturn( 1 );
		return $mockCentralIdLookup;
	}

}