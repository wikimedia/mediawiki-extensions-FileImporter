<?php

namespace FileImporter\Generic\Test;

use FileImporter\Generic\TargetUrl;
use PHPUnit_Framework_TestCase;

class TargetUrlTest extends PHPUnit_Framework_TestCase {

	public function provideConstruction() {
		return [
			[
				'foooooooo',
				false,
				false
			],
			[
				'https://en.wikipedia.org/wiki/File:Foo.jpg',
				true,
				[
					'scheme' => 'https',
					'host' => 'en.wikipedia.org',
					'delimiter' => '://',
					'path' => '/wiki/File:Foo.jpg',
				],
			],
		];
	}

	/**
	 * @dataProvider provideConstruction
	 */
	public function testConstruction( $url, $expectedIsParsable, $expectedParsed ) {
		$targetUrl = new TargetUrl( $url );
		$this->assertEquals( $url, $targetUrl->getUrl() );
		$this->assertEquals( $expectedParsed, $targetUrl->getParsedUrl() );
		$this->assertEquals( $expectedIsParsable, $targetUrl->isParsable() );
	}

}
