<?php

namespace FileImporter\Tests\Remote\MediaWiki;

use Exception;
use FileImporter\Data\SourceUrl;
use FileImporter\Remote\MediaWiki\CentralAuthTokenProvider;
use FileImporter\Remote\MediaWiki\HttpApiLookup;
use FileImporter\Remote\MediaWiki\RemoteApiRequestExecutor;
use FileImporter\Services\Http\HttpRequestExecutor;
use MWHttpRequest;
use PHPUnit4And6Compat;
use User;

/**
 * @covers \FileImporter\Remote\MediaWiki\RemoteApiRequestExecutor
 */
class RemoteApiRequestExecutorTest extends \PHPUnit\Framework\TestCase {
	use PHPUnit4And6Compat;

	public function testGetCsrfToken_success() {
		$centralAuthToken = 'abc' . mt_rand();
		$csrfToken = 'abc' . mt_rand();
		$mockHttpApiLookup = $this->createMock( HttpApiLookup::class );
		$mockHttpApiLookup
			->method( 'getApiUrl' )
			->willReturn( 'https://w.invalid/w/api.php' );
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
		$mockCentralAuthTokenProvider = $this->createMock( CentralAuthTokenProvider::class );
		$mockCentralAuthTokenProvider
			->method( 'getToken' )
			->willReturn( $centralAuthToken );

		$apiRequestExecutor = new RemoteApiRequestExecutor(
			$mockHttpApiLookup, $mockHttpRequestExecutor, $mockCentralAuthTokenProvider );

		$this->assertEquals(
			$csrfToken,
			$apiRequestExecutor->getCsrfToken(
				$this->createMock( SourceUrl::class ),
				$this->createMock( User::class ) )
		);
	}

	public function testGetCsrfToken_failedCentralAuth() {
		$mockHttpApiLookup = $this->createMock( HttpApiLookup::class );
		$mockHttpApiLookup
			->method( 'getApiUrl' )
			->willReturn( 'https://w.invalid/w/api.php' );
		$mockHttpRequestExecutor = $this->createMock( HttpRequestExecutor::class );
		$mockHttpRequestExecutor
			->expects( $this->never() )
			->method( 'execute' );
		$mockCentralAuthTokenProvider = $this->createMock( CentralAuthTokenProvider::class );
		$mockCentralAuthTokenProvider
			->method( 'getToken' )
			->willThrowException( new Exception() );

		$apiRequestExecutor = new RemoteApiRequestExecutor(
			$mockHttpApiLookup, $mockHttpRequestExecutor, $mockCentralAuthTokenProvider );

		$this->assertNull(
			$apiRequestExecutor->getCsrfToken(
				$this->createMock( SourceUrl::class ),
				$this->createMock( User::class ) )
		);
	}

	public function testGetCsrfToken_failedCsrfToken() {
		$centralAuthToken = 'abc' . mt_rand();
		$mockHttpApiLookup = $this->createMock( HttpApiLookup::class );
		$mockHttpApiLookup
			->method( 'getApiUrl' )
			->willReturn( 'https://w.invalid/w/api.php' );
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
		$mockCentralAuthTokenProvider = $this->createMock( CentralAuthTokenProvider::class );
		$mockCentralAuthTokenProvider
			->method( 'getToken' )
			->willReturn( $centralAuthToken );

		$apiRequestExecutor = new RemoteApiRequestExecutor(
			$mockHttpApiLookup, $mockHttpRequestExecutor, $mockCentralAuthTokenProvider );

		$this->assertNull(
			$apiRequestExecutor->getCsrfToken(
				$this->createMock( SourceUrl::class ),
				$this->createMock( User::class ) )
		);
	}

	public function testExecute_successPost() {
		$this->markTestSkipped( 'canUseCentralAuth not easily testable yet.' );
		$queryParams = [ 'a' => 'foo' ];
		$expectedResult = [ 'foo' => 'bar' ];

		$centralAuthToken = 'abc' . mt_rand();
		$csrfToken = 'abc' . mt_rand();
		$mockHttpApiLookup = $this->createMock( HttpApiLookup::class );
		$mockHttpApiLookup
			->method( 'getApiUrl' )
			->willReturn( 'https://w.invalid/w/api.php' );
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
		$mockCentralAuthTokenProvider = $this->createMock( CentralAuthTokenProvider::class );
		$mockCentralAuthTokenProvider
			->method( 'getToken' )
			->willReturn( $centralAuthToken );

		$apiRequestExecutor = new RemoteApiRequestExecutor(
			$mockHttpApiLookup, $mockHttpRequestExecutor, $mockCentralAuthTokenProvider );

		$this->assertEquals(
			$expectedResult,
			$apiRequestExecutor->execute(
				$this->createMock( SourceUrl::class ),
				$this->createMock( User::class ),
				$queryParams,
				true )
		);
	}

}
