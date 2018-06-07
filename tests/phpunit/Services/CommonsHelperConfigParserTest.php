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
				'expectedGoodTemplates' => [],
				'expectedBadTemplates' => [],
				'expectedBadCategories' => [],
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
* GoodTemplate
=== Bad ===
* BadTemplate
== Categories ==
=== Bad ===
* BadCategory
WIKITEXT
				,
				'expectedGoodTemplates' => [ 'GoodTemplate' ],
				'expectedBadTemplates' => [ 'BadTemplate' ],
				'expectedBadCategories' => [ 'BadCategory' ],
			],

			'compressed syntax' => [
				'wikiText' => <<<WIKITEXT
==Templates==
===Good===
* GoodTemplate
===Bad===
*BadTemplate
==Categories==
===Bad===
*BadCategory
WIKITEXT
				,
				'expectedGoodTemplates' => [ 'GoodTemplate' ],
				'expectedBadTemplates' => [ 'BadTemplate' ],
				'expectedBadCategories' => [ 'BadCategory' ],
			],

			'tabs' => [
				'wikiText' => <<<WIKITEXT
==	Templates	==\t

===	Good	===\t

*	GoodTemplate\t

===	Bad	===\t

*	BadTemplate\t

==	Categories	==\t

===	Bad	===\t

*	BadCategory\t
WIKITEXT
				,
				'expectedGoodTemplates' => [ 'GoodTemplate' ],
				'expectedBadTemplates' => [ 'BadTemplate' ],
				'expectedBadCategories' => [ 'BadCategory' ],
			],

			'additional elements to ignore' => [
				'wikiText' => <<<WIKITEXT
<!--
== Templates ==
-->
== Templates ==
=== Good ===
* GoodTemplate
=== Bad ===
<!-- Comment -->
* BadTemplate <!-- Comment -->
*
** 2nd-level comment
== Categories ==
=== Bad ===
{{Comment}}
* <!-- Comment --> BadCategory
<!--
* Disabled
-->
*# 2nd-level comment
*: 2nd-level comment
*; 2nd-level comment
WIKITEXT
				,
				'expectedGoodTemplates' => [ 'GoodTemplate' ],
				'expectedBadTemplates' => [ 'BadTemplate' ],
				'expectedBadCategories' => [ 'BadCategory' ],
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
