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
				'expectedGoodTemplates' => null,
				'expectedBadTemplates' => null,
				'expectedBadCategories' => null,
				'expectedException' => LocalizedImportException::class
			],

			'missing "bad templates" heading' => [
				'wikiText' => "== Templates ==\n=== Good ===\n== Categories ==\n=== Bad ===\n",
				'expectedGoodTemplates' => null,
				'expectedBadTemplates' => null,
				'expectedBadCategories' => null,
				'expectedException' => LocalizedImportException::class
			],

			'missing "good templates" heading' => [
				'wikiText' => "== Templates ==\n=== Bad ===\n== Categories ==\n=== Bad ===\n",
				'expectedGoodTemplates' => null,
				'expectedBadTemplates' => null,
				'expectedBadCategories' => null,
				'expectedException' => LocalizedImportException::class
			],

			'missing "bad categories" heading' => [
				'wikiText' => "== Templates ==\n=== Good ===\n=== Bad ===\n== Categories ==\n",
				'expectedGoodTemplates' => null,
				'expectedBadTemplates' => null,
				'expectedBadCategories' => null,
				'expectedException' => LocalizedImportException::class
			],

			'missing lists' => [
				'wikiText' => "== Templates ==\n=== Good ===\n=== Bad ===\n== Categories ==\n=== Bad ===\n",
				'expectedGoodTemplates' => null,
				'expectedBadTemplates' => null,
				'expectedBadCategories' => null,
				// TODO: I don't think this should throw an exception, as the syntax is fine
				'expectedException' => LocalizedImportException::class
			],

			'empty lists' => [
				'wikiText' => "== Templates ==\n=== Good ===\n*\n=== Bad ===\n*" .
					"\n== Categories ==\n=== Bad ===\n*",
				'expectedGoodTemplates' => [],
				'expectedBadTemplates' => [],
				'expectedBadCategories' => [],
			],

			'simple 1-element lists' => [
				'wikiText' => <<<WIKITEXT
== Templates ==
=== Good ===
* Good
=== Bad ===
* Bad
== Categories ==
=== Bad ===
* Bad
WIKITEXT
				,
				'expectedGoodTemplates' => [ 'Good' ],
				'expectedBadTemplates' => [ 'Bad' ],
				'expectedBadCategories' => [ 'Bad' ],
			],

			'compressed syntax' => [
				'wikiText' => <<<WIKITEXT
==Templates==
===Good===
* Good
===Bad===
*Bad
==Categories==
===Bad===
*Bad
WIKITEXT
				,
				'expectedGoodTemplates' => null,
				'expectedBadTemplates' => null,
				'expectedBadCategories' => null,
				// FIXME: Relax all relevant regular expressions
				'expectedException' => LocalizedImportException::class
			],

			'tabs' => [
				'wikiText' => <<<WIKITEXT
==	Templates	==

=== Good ===

* Good

===	Bad	===

*	Bad

==	Categories	==

===	Bad	===

*	Bad
WIKITEXT
				,
				'expectedGoodTemplates' => null,
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
		array $expectedGoodTemplates = null,
		array $expectedBadTemplates = null,
		array $expectedBadCategories = null,
		$expectedException = null
	) {
		$parser = new CommonsHelperConfigParser( '', $wikiText );
		if ( $expectedException ) {
			$this->setExpectedException( $expectedException );
			$parser->getWikiTextConversions();
		} else {
			$expected = new WikiTextConversions(
				$expectedGoodTemplates,
				$expectedBadTemplates,
				$expectedBadCategories
			);
			$this->assertEquals( $expected, $parser->getWikiTextConversions() );
		}
	}

}
