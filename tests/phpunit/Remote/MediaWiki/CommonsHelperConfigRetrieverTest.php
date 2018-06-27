<?php

namespace FileImporter\Tests\Remote\MediaWiki;

use FileImporter\Data\SourceUrl;
use FileImporter\Remote\MediaWiki\CommonsHelperConfigRetriever;
use FileImporter\Services\Http\HttpRequestExecutor;

/**
 * @covers \FileImporter\Remote\MediaWiki\CommonsHelperConfigRetriever
 *
 * @license GPL-2.0-or-later
 * @author Thiemo Kreuz
 */
class CommonsHelperConfigRetrieverTest extends \PHPUnit\Framework\TestCase {
	use \PHPUnit4And6Compat;

	// TODO: Test incompatible URLs
	// TODO: Test the "missing" code-path
	// TODO: Test all kinds of failures

	public function provideSourceUrls() {
		return [
			[ '//de.wikipedia.org/wiki/Example.svg', 'Data_de.wikipedia' ],
			[ '//en.MediaWiki.org', 'Data_en.mediawiki' ],

			[ '//www.mediawiki.org', 'Data_www.mediawiki' ],
			[ '//www.Wikipedia.org', 'Data_www.wikipedia' ],

			[ '//mediawiki.org', 'Data_www.mediawiki' ],
			[ '//wikipedia.org', 'Data_www.wikipedia' ],

			[ '//en.m.de', 'Data_en.m' ],
			[ '//en.comics.wikia.com', 'Data_en.comics.wikia' ],
		];
	}

	/**
	 * @dataProvider provideSourceUrls
	 */
	public function testSuccess( $sourceUrl, $configPage ) {
		$sourceUrl = new SourceUrl( $sourceUrl );

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
			->with( $this->stringContains( '&titles=' . rawurlencode( $configPage ) . '&' ) )
			->willReturn( $request );

		$retriever = new CommonsHelperConfigRetriever(
			$requestExecutor,
			'<SERVER>',
			'Data ',
			$sourceUrl
		);

		$this->assertTrue( $retriever->retrieveConfiguration() );
		$this->assertStringEndsWith( "/wiki/$configPage", $retriever->getConfigWikiUrl() );
		$this->assertSame( '<WIKITEXT>', $retriever->getConfigWikiText() );
	}

}
