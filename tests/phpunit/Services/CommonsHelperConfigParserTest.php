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
				'wikiText' => "== Templates ==\n=== Good ===\n=== Bad ===\n=== Transfer ===" .
					"\n== Categories ==\n=== Bad ===\n",
				'expectedGoodTemplates' => [],
				'expectedBadTemplates' => [],
				'expectedBadCategories' => [],
			],

			'empty lists' => [
				'wikiText' => <<<WIKITEXT
== Templates ==
=== Good ===
*
=== Bad ===
*
=== Transfer ===
== Categories ==
=== Bad ===
*
WIKITEXT
				,
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
=== Transfer ===
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
===Transfer===
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

=== Transfer ===
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
=== Transfer ===
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
				$expectedBadCategories,
				[]
			);
			$this->assertEquals( $expected, $parser->getWikiTextConversions() );
		}
	}

	public function provideTransferRules() {
		return [
			'empty' => [
				'wikiText' => '',
				'expected' => [],
			],
			'empty <dt> element' => [
				'wikiText' => ';',
				'expected' => [],
			],
			'no <dd> element' => [
				'wikiText' => ';Local',
				'expected' => [],
			],
			'empty <dd> element' => [
				'wikiText' => ';Local:',
				'expected' => [],
			],
			'empty <dd> element on next line' => [
				'wikiText' => ";Local\n:",
				'expected' => [],
			],
			'to many newlines' => [
				'wikiText' => ";Local\n\n:Commons",
				'expected' => [],
			],
			'bad parameter syntax on local side' => [
				'wikiText' => ";Local|param:Commons",
				'expected' => [],
			],

			'basic 1-line syntax' => [
				'wikiText' => ';Local:Commons',
				'expected' => [ 'Local' => 'Commons' ],
			],
			'basic 2-line syntax' => [
				'wikiText' => ";Local\n:Commons",
				'expected' => [ 'Local' => 'Commons' ],
			],
			'empty parameter list' => [
				'wikiText' => ";Local:Commons|",
				'expected' => [ 'Local' => 'Commons' ],
			],
			'one basic parameter' => [
				'wikiText' => ";Local:Commons|local_param=commons_param",
				'expected' => [ 'Local' => 'Commons' ],
			],
			'additional whitespace' => [
				'wikiText' => "; Local : Commons | local_param = commons_param",
				'expected' => [ 'Local' => 'Commons' ],
			],
		];
	}

	/**
	 * @dataProvider provideTransferRules
	 */
	public function testTransferRules( $wikiText, array $expected ) {
		$wikiText = "== Templates ==\n=== Good ===\n=== Bad ===\n=== Transfer ===\n$wikiText\n" .
			"== Categories ==\n=== Bad ===";
		$parser = new CommonsHelperConfigParser( '', $wikiText );
		$expected = new WikiTextConversions( [], [], [], $expected );
		$this->assertEquals( $expected, $parser->getWikiTextConversions() );
	}

}
