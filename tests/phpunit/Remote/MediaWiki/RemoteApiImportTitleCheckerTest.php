<?php

namespace FileImporter\Tests\Remote\MediaWiki;

use FileImporter\Data\SourceUrl;
use FileImporter\Remote\MediaWiki\HttpApiLookup;
use FileImporter\Remote\MediaWiki\RemoteApiImportTitleChecker;
use FileImporter\Services\Http\HttpRequestExecutor;
use MWHttpRequest;
use Psr\Log\LoggerInterface;

/**
 * @covers \FileImporter\Remote\MediaWiki\RemoteApiImportTitleChecker
 *
 * @license GPL-2.0-or-later
 * @author Thiemo Kreuz
 */
class RemoteApiImportTitleCheckerTest extends \PHPUnit\Framework\TestCase {

	public function provideJsonResponses() {
		return [
			[ '{"query":{"pages":[{"missing":true}]}}', true, 0 ],
			[ '{"query":{"pages":[{"missing":""}]}}', true, 0 ],
			[ '{"query":{"pages":[{"pageid":1}]}}', false, 0 ],
			[ '{"query":{"pages":{}}}', false, 1 ],
			[ '{"query":{}}', false, 1 ],
			[ '{}', false, 1 ],
			[ '', false, 1 ],
		];
	}

	/**
	 * @dataProvider provideJsonResponses
	 */
	public function test( $json, $expected, $expectedLoggerCalls ) {
		$sourceUrl = new SourceUrl( '//SOURCE.URL' );

		$apiLookup = $this->createMock( HttpApiLookup::class );
		$apiLookup->expects( $this->once() )
			->method( 'getApiUrl' )
			->with( $sourceUrl )
			->willReturn( '<API>' );

		$httpRequest = $this->createMock( MWHttpRequest::class );
		$httpRequest->expects( $this->once() )
			->method( 'getContent' )
			->willReturn( $json );

		$requestExecutor = $this->createMock( HttpRequestExecutor::class );
		$requestExecutor->expects( $this->once() )
			->method( 'execute' )
			->with( '<API>', [
				'format' => 'json',
				'action' => 'query',
				'titles' => 'File:<TITLE>',
				'formatversion' => 2,
			] )
			->willReturn( $httpRequest );

		$logger = $this->createMock( LoggerInterface::class );
		$logger->expects( $this->exactly( $expectedLoggerCalls ) )
			->method( 'error' );

		$checker = new RemoteApiImportTitleChecker(
			$apiLookup,
			$requestExecutor,
			$logger
		);

		$this->assertSame( $expected, $checker->importAllowed( $sourceUrl, '<TITLE>' ) );
	}

}
