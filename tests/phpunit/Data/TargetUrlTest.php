<?php

namespace FileImporter\Data\Test;

use FileImporter\Data\SourceUrl;
use InvalidArgumentException;

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
		$this->setExpectedException( InvalidArgumentException::class );
		new SourceUrl( $input );
	}

	public function provideValidConstruction() {
		return [
			[
				'https://en.wikipedia.org/wiki/File:Foo.jpg',
				true,
				[
					'scheme' => 'https',
					'host' => 'en.wikipedia.org',
					'delimiter' => '://',
					'path' => '/wiki/File:Foo.jpg',
				],
				'en.wikipedia.org',
			],
		];
	}

	/**
	 * @dataProvider provideValidConstruction
	 */
	public function testValidConstruction(
		$url,
		$expectedIsParsable,
		$expectedParsed,
		$expectedDomain
	) {
		$sourceUrl = new SourceUrl( $url );
		$this->assertEquals( $url, $sourceUrl->getUrl() );
		$this->assertEquals( $expectedParsed, $sourceUrl->getParsedUrl() );
		$this->assertEquals( $expectedIsParsable, $sourceUrl->isParsable() );
		$this->assertEquals( $expectedDomain, $sourceUrl->getHost() );
	}

}
