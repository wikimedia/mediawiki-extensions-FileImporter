<?php

namespace FileImporter\Remote\MediaWiki\Test;

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
class RemoteApiImportTitleCheckerTest extends \MediaWikiTestCase {
	use \PHPUnit4And6Compat;

	public function provideJsonResponses() {
		return [
			[ '{"query":{"pages":{"-1":{}}}}', true, 0 ],
			[ '{"query":{"pages":{"1":{}}}}', false, 0 ],
			[ '{"query":{"pages":{}}}', false, 0 ],
			[ '{"query":{}}', false, 1 ],
			[ '{}', false, 1 ],
			[ '', false, 1 ],
			[ null, false, 1 ],
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
			->with( '<API>?format=json&action=query&prop=revisions&titles=File%3A%3CTITLE%3E' )
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
