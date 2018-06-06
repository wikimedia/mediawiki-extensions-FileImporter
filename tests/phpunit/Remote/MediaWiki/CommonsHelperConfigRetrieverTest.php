<?php

namespace FileImporter\Tests\Remote\MediaWiki;

use FileImporter\Data\SourceUrl;
use FileImporter\Remote\MediaWiki\CommonsHelperConfigRetriever;
use FileImporter\Services\Http\HttpRequestExecutor;

/**
 * @covers \FileImporter\Remote\MediaWiki\CommonsHelperConfigRetriever
 */
class CommonsHelperConfigRetrieverTest extends \PHPUnit\Framework\TestCase {
	use \PHPUnit4And6Compat;

	// TODO: Test the special www.mediawiki code-path
	// TODO: Test incompatible URLs
	// TODO: Test the "missing" code-path
	// TODO: Test all kinds of failures

	public function testSuccess() {
		$sourceUrl = new SourceUrl( '//de.wikipedia.org/wiki/Example.svg' );

		$request = $this->createMock( \MWHttpRequest::class );
		$request->method( 'getContent' )
			->willReturn( json_encode( [
				'query' => [
					'pages' => [
						[
							'revisions' => [
								[ 'content' => '<WIKITEXT>' ]
							],
						],
					],
				],
			] ) );

		$requestExecutor = $this->createMock( HttpRequestExecutor::class );
		$requestExecutor->method( 'execute' )
			->willReturn( $request );

		$retriever = new CommonsHelperConfigRetriever(
			$requestExecutor,
			'<SERVER>',
			'<BASE>',
			$sourceUrl
		);

		$this->assertTrue( $retriever->retrieveConfiguration() );
		$this->assertSame(
			'<SERVER>/wiki/<BASE>de.wikipedia',
			$retriever->getConfigWikiUrl()
		);
		$this->assertSame( '<WIKITEXT>', $retriever->getConfigWikiText() );
	}

}
