<?php

namespace FileImporter\Remote\MediaWiki\Test;

use FileImporter\Data\SourceUrl;
use FileImporter\Remote\MediaWiki\InterwikiTablePrefixLookup;
use MediaWiki\Interwiki\InterwikiLookupAdapter;
use MediaWikiTestCase;

/**
 * @covers \FileImporter\Remote\MediaWiki\InterwikiTablePrefixLookup
 *
 * @license GPL-2.0-or-later
 * @author Christoph Jauera <christoph.jauera@wikimedia.de>
 */
class InterwikiTablePrefixLookupTest extends MediaWikiTestCase {

	public function provideGetPrefixFromConfig() {
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
	 * @dataProvider provideGetPrefixFromConfig
	 */
	public function testGetPrefixFromConfig( array $global, $source, $validPrefix, $expected ) {
		$this->setMwGlobals( [
			'wgFileImporterInterWikiMap' => $global,
		] );

		$sourceUrlPrefixer = new InterwikiTablePrefixLookup(
			$this->createInterWikiLookupMock( $validPrefix, [] )
		);

		$this->assertSame( $expected, $sourceUrlPrefixer->getPrefix(
			new SourceUrl( $source ) )
		);
	}

	public function provideGetPrefixFromTable() {
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
				'//wikipedia.org/wiki/',
				''
			],
		];
	}

	/**
	 * @dataProvider provideGetPrefixFromTable
	 */
	public function testGetPrefixFromTable( array $iwMap, $source, $expected ) {
		$sourceUrlPrefixer = new InterwikiTablePrefixLookup(
			$this->createInterWikiLookupMock( true, $iwMap )
		);

		$this->assertSame( $expected, $sourceUrlPrefixer->getPrefix(
			new SourceUrl( $source ) )
		);
	}

	public function testGetPrefixFromTableCache() {
		$iwMock = $this->createMock( InterwikiLookupAdapter::class );
		$iwMock->expects( $this->once() )
			->method( 'getAllPrefixes' )
			->willReturn( [ [
				'iw_url' => 'https://de.wikipedia.org/wiki/$1',
				'iw_prefix' => 'wiki'
			] ] );

		$sourceUrl = new SourceUrl( '//de.wikipedia.org/wiki' );

		$sourceUrlPrefixer = new InterwikiTablePrefixLookup( $iwMock );

		$sourceUrlPrefixer->getPrefix( $sourceUrl );
		$sourceUrlPrefixer->getPrefix( $sourceUrl );
	}

	/**
	 * @param bool $validPrefix
	 * @param array[] $iwMap
	 * @return InterwikiLookupAdapter
	 */
	private function createInterWikiLookupMock( $validPrefix, array $iwMap ) {
		$mock = $this->createMock( InterwikiLookupAdapter::class );
		$mock->method( 'isValidInterwiki' )
			->willReturn( $validPrefix );
		$mock->method( 'getAllPrefixes' )
			->willReturn( $iwMap );

		return $mock;
	}

}
