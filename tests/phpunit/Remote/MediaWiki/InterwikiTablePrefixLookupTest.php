<?php

namespace FileImporter\Remote\MediaWiki\Test;

use FileImporter\Data\SourceUrl;
use FileImporter\Remote\MediaWiki\InterwikiTablePrefixLookup;
use MediaWiki\Interwiki\InterwikiLookupAdapter;
use MediaWikiTestCase;
use Psr\Log\NullLogger;

/**
 * @covers \FileImporter\Remote\MediaWiki\InterwikiTablePrefixLookup
 *
 * @license GPL-2.0-or-later
 * @author Christoph Jauera <christoph.jauera@wikimedia.de>
 */
class InterwikiTablePrefixLookupTest extends MediaWikiTestCase {

	public function provideGetPrefix() {
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
	 * @dataProvider provideGetPrefix
	 */
	public function testGetPrefix( $global, $source, $validPrefix, $expected ) {
		$this->setMwGlobals( [
			'wgFileImporterInterWikiMap' => $global,
		] );

		$lookupMock = $this->createInterWikiLookupMock( $validPrefix );

		$sourceUrlPrefixer = new InterwikiTablePrefixLookup(
			$lookupMock,
			new NullLogger()
		);

		$this->assertSame( $expected, $sourceUrlPrefixer->getPrefix(
			new SourceUrl( $source ) )
		);
	}

	/**
	 * @param $validPrefix
	 * @return InterwikiLookupAdapter
	 */
	private function createInterWikiLookupMock( $validPrefix ) {
		$mock = $this->createMock( InterwikiLookupAdapter::class );
		$mock->method( 'isValidInterwiki' )
			->willReturn( $validPrefix );

		return $mock;
	}
}
