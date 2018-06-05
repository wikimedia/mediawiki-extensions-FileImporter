<?php

namespace FileImporter\Tests\Services;

use FileImporter\Data\WikiTextConversions;
use FileImporter\Exceptions\LocalizedImportException;
use FileImporter\Services\CommonsHelperConfigParser;

/**
 * @covers \FileImporter\Services\CommonsHelperConfigParser
 */
class CommonsHelperConfigParserTest extends \PHPUnit\Framework\TestCase {
	use \PHPUnit4And6Compat;

	public function provideCommonsHelperConfig() {
		return [
			'empty' => [
				'wikiText' => '',
				'expectedBadTemplates' => null,
				'expectedBadCategories' => null,
				'expectedException' => LocalizedImportException::class
			],

			'missing "bad templates" heading' => [
				'wikiText' => "== Templates ==\n== Categories ==\n=== Bad ===\n",
				'expectedBadTemplates' => null,
				'expectedBadCategories' => null,
				'expectedException' => LocalizedImportException::class
			],

			'missing "bad categories" heading' => [
				'wikiText' => "== Templates ==\n=== Bad ===\n== Categories ==\n",
				'expectedBadTemplates' => null,
				'expectedBadCategories' => null,
				'expectedException' => LocalizedImportException::class
			],

			'missing lists' => [
				'wikiText' => "== Templates ==\n=== Bad ===\n== Categories ==\n=== Bad ===\n",
				'expectedBadTemplates' => null,
				'expectedBadCategories' => null,
				// TODO: I don't think this should throw an exception, as the syntax is fine
				'expectedException' => LocalizedImportException::class
			],

			'empty lists' => [
				'wikiText' => "== Templates ==\n=== Bad ===\n*\n== Categories ==\n=== Bad ===\n*",
				'expectedBadTemplates' => [],
				'expectedBadCategories' => [],
			],

			'simple 1-element lists' => [
				'wikiText' => <<<WIKITEXT
== Templates ==
=== Bad ===
* Bad
== Categories ==
=== Bad ===
* Bad
WIKITEXT
				,
				'expectedBadTemplates' => [ 'Bad' ],
				'expectedBadCategories' => [ 'Bad' ],
			],

			'compressed syntax' => [
				'wikiText' => <<<WIKITEXT
==Templates==
===Bad===
*Bad
==Categories==
===Bad===
*Bad
WIKITEXT
				,
				'expectedBadTemplates' => null,
				'expectedBadCategories' => null,
				// FIXME: Relax all relevant regular expressions
				'expectedException' => LocalizedImportException::class
			],

			'tabs' => [
				'wikiText' => <<<WIKITEXT
==	Templates	==

===	Bad	===

*	Bad

==	Categories	==

===	Bad	===

*	Bad
WIKITEXT
				,
				'expectedBadTemplates' => null,
				'expectedBadCategories' => null,
				// FIXME: Relax all relevant regular expressions
				'expectedException' => LocalizedImportException::class
			],
		];
	}

	/**
	 * @dataProvider provideCommonsHelperConfig
	 */
	public function testParser(
		$wikiText,
		array $expectedBadTemplates = null,
		array $expectedBadCategories = null,
		$expectedException = null
	) {
		$parser = new CommonsHelperConfigParser( '', $wikiText );
		if ( $expectedException ) {
			$this->setExpectedException( $expectedException );
			$parser->getWikiTextConversions();
		} else {
			$expected = new WikiTextConversions( $expectedBadTemplates, $expectedBadCategories );
			$this->assertEquals( $expected, $parser->getWikiTextConversions() );
		}
	}

}
