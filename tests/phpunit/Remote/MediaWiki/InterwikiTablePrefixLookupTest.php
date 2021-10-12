<?php

namespace FileImporter\Tests\Remote\MediaWiki;

use FileImporter\Data\SourceUrl;
use FileImporter\Exceptions\HttpRequestException;
use FileImporter\Remote\MediaWiki\HttpApiLookup;
use FileImporter\Remote\MediaWiki\InterwikiTablePrefixLookup;
use FileImporter\Services\Http\HttpRequestExecutor;
use Interwiki;
use MediaWiki\Interwiki\InterwikiLookup;
use MWHttpRequest;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \FileImporter\Remote\MediaWiki\InterwikiTablePrefixLookup
 *
 * @license GPL-2.0-or-later
 * @author Christoph Jauera <christoph.jauera@wikimedia.de>
 */
class InterwikiTablePrefixLookupTest extends \MediaWikiIntegrationTestCase {

	public function provideGetPrefixFromLegacyConfig() {
		return [
			'interWikiMap contains host' => [
				[ 'de.wikipedia.org' => 'wiki:de' ],
				'//de.wikipedia.org/wiki/',
				true,
				'wiki:de'
			],
			'interWikiMap does not contain host' => [
				[],
				'//de.wikipedia.org/wiki/',
				true,
				''
			],
			'interwiki id configured is wrong' => [
				[ 'de.wikipedia.org' => 'wiki:de' ],
				'//de.wikipedia.org/wiki/',
				false,
				''
			],
		];
	}

	/**
	 * @deprecated
	 * @dataProvider provideGetPrefixFromLegacyConfig
	 */
	public function testGetPrefixFromLegacyConfig(
		array $interWikiMap, $source, $validPrefix, $expected
	) {
		$prefixLookup = new InterwikiTablePrefixLookup(
			$this->createInterWikiLookupMock( $validPrefix, [] ),
			$this->createMock( HttpApiLookup::class ),
			$this->createMock( HttpRequestExecutor::class ),
			$interWikiMap
		);

		$this->assertSame(
			$expected,
			$prefixLookup->getPrefix( new SourceUrl( $source ) )
		);
	}

	public function provideGetPrefixFromLocalTable() {
		return [
			'interWiki table contains host' => [
				[
					[
						'iw_url' => 'https://de.wikipedia.org/wiki/$1',
						'iw_prefix' => 'wiki'
					],
				],
				'//de.wikipedia.org/wiki/',
				'wiki'
			],
			'interWiki table does not contain host' => [
				[
					[
						'iw_url' => 'https://en.wikipedia.org/wiki/$1',
						'iw_prefix' => 'wiki'
					],
				],
				'//wikivoyage.org/wiki/',
				''
			],
			'accept aliases with identical URLs' => [
				[
					[
						'iw_url' => 'http://www.wikia.com/wiki/$1',
						'iw_prefix' => 'wikia'
					],
					[
						'iw_url' => 'http://www.wikia.com/wiki/$1',
						'iw_prefix' => 'wikicities'
					],
				],
				'//www.wikia.com/',
				'wikia'
			],
			'prefer shortest alias' => [
				[
					[
						'iw_url' => 'http://www.wikia.com/wiki/$1',
						'iw_prefix' => '1-wikicities'
					],
					[
						'iw_url' => 'http://www.wikia.com/wiki/$1',
						'iw_prefix' => '3-wikia'
					],
					[
						'iw_url' => 'http://www.wikia.com/wiki/$1',
						'iw_prefix' => '2-wikia'
					],
					[
						'iw_url' => 'http://www.wikia.com/wiki/$1',
						'iw_prefix' => '0-wikicities'
					],
				],
				'//www.wikia.com/',
				'2-wikia'
			],
			'be sloppy about ambiguous hosts' => [
				[
					[
						'iw_url' => 'http://www.tejo.org/vikio/$1',
						'iw_prefix' => 'tejo'
					],
					[
						'iw_url' => 'http://www.tejo.org/uea/$1',
						'iw_prefix' => 'uea'
					],
					[
						'iw_url' => 'http://www.tejo.org/3rd/$1',
						'iw_prefix' => '3rd'
					],
				],
				'//www.tejo.org/',
				'3rd'
			],
		];
	}

	/**
	 * @dataProvider provideGetPrefixFromLocalTable
	 */
	public function testGetPrefixFromLocalTable( array $iwMap, $source, $expected ) {
		$prefixLookup = new InterwikiTablePrefixLookup(
			$this->createInterWikiLookupMock( true, $iwMap ),
			$this->createMock( HttpApiLookup::class ),
			$this->createInterwikiApi()
		);

		$this->assertSame(
			$expected,
			$prefixLookup->getPrefix( new SourceUrl( $source ) )
		);
	}

	public function testGetPrefixFromLocalTableCache() {
		$iwMock = $this->createMock( InterwikiLookup::class );
		$iwMock->expects( $this->once() )
			->method( 'getAllPrefixes' )
			->willReturn( [ [
				'iw_url' => 'https://de.wikipedia.org/wiki/$1',
				'iw_prefix' => 'wiki'
			] ] );

		$sourceUrl = new SourceUrl( '//de.wikipedia.org/wiki' );

		$prefixLookup = new InterwikiTablePrefixLookup(
			$iwMock,
			$this->createMock( HttpApiLookup::class ),
			$this->createMock( HttpRequestExecutor::class )
		);

		$this->assertSame(
			'wiki',
			$prefixLookup->getPrefix( $sourceUrl )
		);
	}

	/**
	 * @param bool $validPrefix
	 * @param array[] $iwMap
	 * @return InterwikiLookup
	 */
	private function createInterWikiLookupMock(
		$validPrefix,
		array $iwMap
	): InterwikiLookup {
		$mock = $this->createMock( InterwikiLookup::class );
		$mock->method( 'isValidInterwiki' )
			->willReturn( $validPrefix );
		$mock->method( 'getAllPrefixes' )
			->willReturn( $iwMap );
		$mockInterwiki = $this->createMock( Interwiki::class );
		$mockInterwiki->method( 'getAPI' )
			->willReturn( '//w.invalid/w/api.php' );
		$mock->method( 'fetch' )->willReturn( $mockInterwiki );

		return $mock;
	}

	public function provideIntermediaryCases() {
		return [
			'Successful get through parent domain' => [
				[
					[
						'iw_url' => 'https://en.wikisource.org/wiki/$1',
						'iw_prefix' => 'wikisource'
					],
				],
				[
					[
						'prefix' => 'fr',
						'url' => 'https://fr.wikisource.org/wiki/$1'
					]
				],
				'//fr.wikisource.org/wiki/',
				'wikisource:fr'
			],
			'Successful get through parent domain, labs' => [
				[
					[
						'iw_url' => 'https://en.wikisource.beta.wmflabs.org/wiki/$1',
						'iw_prefix' => 'wikisource'
					],
				],
				[
					[
						'prefix' => 'fr',
						'url' => 'https://fr.wikisource.beta.wmflabs.org/wiki/$1'
					],
					[
						'prefix' => 'guc',
						'url' => 'https://tools.wmflabs.org/guc/$1'
					]
				],
				'//fr.wikisource.beta.wmflabs.org/wiki/',
				'wikisource:fr'
			],
			'fail after hop' => [
				[
					[
						'iw_url' => 'https://en.wikisource.org/wiki/$1',
						'iw_prefix' => 'wikisource'
					],
				],
				[
					[
						'prefix' => 'fr',
						'url' => 'https://fr.wikisource.org/wiki/$1'
					]
				],
				'//invalid.wikisource.org/wiki/',
				''
			],
			// TODO: assert never() calls an intermediary API
			'fail without hop' => [
				[
					[
						'iw_url' => 'https://en.wikisource.org/wiki/$1',
						'iw_prefix' => 'wikisource'
					],
				],
				[],
				'//en.wikiinvalid.invalid/wiki/',
				''
			]
		];
	}

	/**
	 * @dataProvider provideIntermediaryCases
	 *
	 * @param array $localIwMap
	 * @param array $remoteIwMap
	 * @param string $source
	 * @param string $expectedPrefix
	 */
	public function testGetPrefix_throughIntermediary(
		array $localIwMap, array $remoteIwMap, $source, $expectedPrefix
	) {
		$prefixLookup = new InterwikiTablePrefixLookup(
			$this->createInterWikiLookupMock( true, $localIwMap ),
			$this->createMock( HttpApiLookup::class ),
			$this->createInterwikiApi( $remoteIwMap )
		);

		$this->assertSame(
			$expectedPrefix,
			$prefixLookup->getPrefix( new SourceUrl( $source ) )
		);
	}

	private function createInterwikiApi( array $iwMap = [] ): HttpRequestExecutor {
		$remoteContent = $this->createMock( MWHttpRequest::class );
		$remoteContent->method( 'getContent' )
			->willReturn( json_encode( [ 'query' => [ 'interwikimap' => $iwMap ] ] ) );
		$httpRequestExecutor = $this->createMock( HttpRequestExecutor::class );
		$httpRequestExecutor->method( 'execute' )->willReturn( $remoteContent );
		return $httpRequestExecutor;
	}

	public function testGetPrefix_secondHop_apiFallback() {
		$mockLookup = $this->createMock( InterwikiLookup::class );
		$mockLookup->method( 'isValidInterwiki' )
			->willReturn( true );
		$mockLookup->method( 'getAllPrefixes' )
			->willReturn( [
				[
					'iw_url' => 'https://en.wikisource.org/wiki/$1',
					'iw_prefix' => 'wikisource'
				],
			] );
		$mockInterwiki = $this->createMock( Interwiki::class );
		$mockInterwiki->method( 'getAPI' )
			->willReturn( '' );
		$mockInterwiki->method( 'getURL' )
			->willReturn( '//en.wikisource.org/wiki/' );
		$mockLookup->method( 'fetch' )->willReturn( $mockInterwiki );

		$mockApiLookup = $this->createMock( HttpApiLookup::class );
		$mockApiLookup
			->expects( $this->once() )
			->method( 'getApiUrl' )
			->willReturn( '//w.invalid/w/api.php' );

		$prefixLookup = new InterwikiTablePrefixLookup(
			$mockLookup,
			$mockApiLookup,
			$this->createInterwikiApi( [
				[
					'prefix' => 'fr',
					'url' => 'https://fr.wikisource.org/wiki/$1'
				],
			] )
		);

		$this->assertSame(
			'wikisource:fr',
			$prefixLookup->getPrefix( new SourceUrl( '//fr.wikisource.org/wiki/' ) )
		);
	}

	public function testGetPrefix_secondHop_networkFail() {
		$mockRequestExecutor = $this->createMock( HttpRequestExecutor::class );
		$mockRequestExecutor->method( 'execute' )
			->willThrowException( $this->createMock( HttpRequestException::class ) );

		$prefixLookup = new InterwikiTablePrefixLookup(
			$this->createInterWikiLookupMock( true, [
				[
					'iw_url' => 'https://en.wikisource.org/wiki/$1',
					'iw_prefix' => 'wikisource'
				],
			] ),
			$this->createMock( HttpApiLookup::class ),
			$mockRequestExecutor
		);

		$this->assertSame(
			'',
			$prefixLookup->getPrefix( new SourceUrl( '//fr.wikisource.org/wiki/' ) )
		);
	}

	public function testGetPrefix_secondHop_interwikiCorrupt() {
		$mockInterwikiLookup = $this->createMock( InterwikiLookup::class );
		$mockInterwikiLookup->method( 'isValidInterwiki' )
			->willReturn( true );
		$mockInterwikiLookup->method( 'getAllPrefixes' )
			->willReturn( [
				[
					'iw_url' => 'https://en.wikisource.org/wiki/$1',
					'iw_prefix' => 'wikisource'
				],
			] );

		$prefixLookup = new InterwikiTablePrefixLookup(
			$mockInterwikiLookup,
			$this->createMock( HttpApiLookup::class ),
			$this->createMock( HttpRequestExecutor::class )
		);

		$this->assertSame(
			'',
			$prefixLookup->getPrefix( new SourceUrl( '//fr.wikisource.org/wiki/' ) )
		);
	}

	public function testTwoHopLookup_withoutSubdomain() {
		/** @var InterwikiTablePrefixLookup $lookup */
		$lookup = TestingAccessWrapper::newFromObject( new InterwikiTablePrefixLookup(
			$this->createInterWikiLookupMock( false, [
				[ 'iw_url' => '//bad.org', 'iw_prefix' => 'bad' ],
			] ),
			$this->createMock( HttpApiLookup::class ),
			$this->createInterwikiApi( [ [ 'url' => '//good.org', 'prefix' => 'good' ] ] )
		) );

		$this->assertNull(
			$lookup->getTwoHopPrefixThroughIntermediary( 'good.org' ),
			'"good.org" should not be reduced to "org", and then match "bad.org"'
		);
	}

	public function testPrefetchParentDomainToUrlMap() {
		$mockLookup = new InterwikiTablePrefixLookup(
			$this->createInterWikiLookupMock( true, [
				[
					'iw_url' => 'https://en.wikisource.org/wiki/$1',
					'iw_prefix' => 'wikisource'
				],
				[
					'iw_url' => 'https://skipme.org/wiki/$1',
					'iw_prefix' => 'skipme'
				],
			] ),
			$this->createMock( HttpApiLookup::class ),
			$this->createMock( HttpRequestExecutor::class )
		);
		/** @var InterwikiTablePrefixLookup $mockLookup */
		$mockLookup = TestingAccessWrapper::newFromObject( $mockLookup );

		$this->assertSame(
			[ 'wikisource.org' => 'en.wikisource.org' ],
			$mockLookup->prefetchParentDomainToHostMap()
		);
	}

}
