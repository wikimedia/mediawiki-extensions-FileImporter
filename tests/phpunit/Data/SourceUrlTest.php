<?php

namespace FileImporter\Tests\Data;

use FileImporter\Data\SourceUrl;
use FileImporter\Exceptions\InvalidArgumentException;

/**
 * @covers \FileImporter\Data\SourceUrl
 *
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class SourceUrlTest extends \PHPUnit\Framework\TestCase {

	public function provideInvalidConstruction() {
		return [
			[ 'foooooooo' ],
		];
	}

	/**
	 * @dataProvider provideInvalidConstruction
	 */
	public function testInvalidConstruction( $input ) {
		$this->expectException( InvalidArgumentException::class );
		new SourceUrl( $input );
	}

	public function provideValidConstruction() {
		return [
			[
				'https://en.wikipedia.org/wiki/File:Foo.jpg',
				[
					'scheme' => 'https',
					'host' => 'en.wikipedia.org',
					'delimiter' => '://',
					'path' => '/wiki/File:Foo.jpg',
				],
				'en.wikipedia.org',
			],
			[
				" //wiki/X.svg\n",
				[
					'scheme' => '',
					'host' => 'wiki',
					'delimiter' => '//',
					'path' => '/X.svg',
				],
				'wiki',
			],
		];
	}

	/**
	 * @dataProvider provideValidConstruction
	 */
	public function testValidConstruction(
		$url,
		$expectedParsed,
		$expectedDomain
	) {
		$sourceUrl = new SourceUrl( $url );
		$this->assertSame( trim( $url ), $sourceUrl->getUrl() );
		$this->assertEquals( $expectedParsed, $sourceUrl->getParsedUrl() );
		$this->assertSame( $expectedDomain, $sourceUrl->getHost() );
		$this->assertSame( $sourceUrl->getUrl(), (string)$sourceUrl );
	}

}
