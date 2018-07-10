<?php

namespace FileImporter\Tests\Services;

use FileImporter\Data\WikiTextConversions;
use FileImporter\Exceptions\LocalizedImportException;
use FileImporter\Services\CommonsHelperConfigParser;

/**
 * @covers \FileImporter\Services\CommonsHelperConfigParser
 *
 * @license GPL-2.0-or-later
 * @author Thiemo Kreuz
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
				'expectedException' => '<Categories>'
			],

			'missing "bad templates" heading' => [
				'wikiText' => "== Templates ==\n=== Good ===\n== Categories ==\n=== Bad ===\n",
				'expectedGoodTemplates' => null,
				'expectedBadTemplates' => null,
				'expectedBadCategories' => null,
				'expectedException' => '<Templates/Bad>'
			],

			'missing "good templates" heading' => [
				'wikiText' => "== Templates ==\n=== Bad ===\n== Categories ==\n=== Bad ===\n",
				'expectedGoodTemplates' => null,
				'expectedBadTemplates' => null,
				'expectedBadCategories' => null,
				'expectedException' => '<Templates/Good>'
			],

			'missing "transfer templates" heading' => [
				'wikiText' => "== Categories ==\n=== Bad ===\n" .
					"== Templates ==\n=== Good ===\n=== Bad ===\n=== Remove ===",
				'expectedGoodTemplates' => null,
				'expectedBadTemplates' => null,
				'expectedBadCategories' => null,
				'expectedException' => '<Templates/Transfer>'
			],

			'missing "bad categories" heading' => [
				'wikiText' => "== Templates ==\n=== Good ===\n=== Bad ===\n== Categories ==\n",
				'expectedGoodTemplates' => null,
				'expectedBadTemplates' => null,
				'expectedBadCategories' => null,
				'expectedException' => '<Categories/Bad>'
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
		if ( $expectedException !== null ) {
			$this->setExpectedException( LocalizedImportException::class, $expectedException );
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
				'wikiText' => ';Source',
				'expected' => [],
			],
			'empty <dd> element' => [
				'wikiText' => ';Source:',
				'expected' => [],
			],
			'empty <dd> element on next line' => [
				'wikiText' => ";Source\n:",
				'expected' => [],
			],
			'to many newlines' => [
				'wikiText' => ";Source\n\n:Target",
				'expected' => [],
			],
			'bad parameter syntax on local side' => [
				'wikiText' => ";Source|param:Target",
				'expected' => [],
			],

			'basic 1-line syntax' => [
				'wikiText' => ';Source:Target',
				'expected' => [ 'Source' => 'Target' ],
			],
			'basic 2-line syntax' => [
				'wikiText' => ";Source\n:Target",
				'expected' => [ 'Source' => 'Target' ],
			],
			'empty parameter list' => [
				'wikiText' => ";Source:Target|",
				'expected' => [ 'Source' => 'Target' ],
			],

			'one basic parameter' => [
				'wikiText' => ";Source:Target|target_param=source_param",
				'expected' => [ 'Source' => [
					'targetTemplate' => 'Target',
					'parameters' => [ 'target_param' => [
						'addIfMissing' => false,
						'addLanguageTemplate' => false,
						'sourceParameters' => 'source_param',
					] ],
				] ],
			],
			'additional whitespace' => [
				'wikiText' => "; Source : Target | target_param = source_param",
				'expected' => [ 'Source' => [
					'targetTemplate' => 'Target',
					'parameters' => [ 'target_param' => [
						'addIfMissing' => false,
						'addLanguageTemplate' => false,
						'sourceParameters' => 'source_param',
					] ],
				] ],
			],
			'+add syntax' => [
				'wikiText' => ";Source:Target|+target_param=source_param",
				'expected' => [ 'Source' => [
					'targetTemplate' => 'Target',
					'parameters' => [ 'target_param' => [
						'addIfMissing' => true,
						'addLanguageTemplate' => false,
						'value' => 'source_param',
					] ],
				] ],
			],
			'@language parameter syntax' => [
				'wikiText' => ";Source:Target|@target_param=source_param",
				'expected' => [ 'Source' => [
					'targetTemplate' => 'Target',
					'parameters' => [ 'target_param' => [
						'addIfMissing' => false,
						'addLanguageTemplate' => true,
						'sourceParameters' => 'source_param',
					] ],
				] ],
			],
			'+@ combination leaves a meaningless @ behind' => [
				'wikiText' => ";Source:Target|+@target_param=source_param",
				'expected' => [ 'Source' => [
					'targetTemplate' => 'Target',
					'parameters' => [ '@target_param' => [
						'addIfMissing' => true,
						'addLanguageTemplate' => false,
						'value' => 'source_param',
					] ],
				] ],
			],
			'@+ combination leaves a meaningless + behind' => [
				'wikiText' => ";Source:Target|@+target_param=source_param",
				'expected' => [ 'Source' => [
					'targetTemplate' => 'Target',
					'parameters' => [ '+target_param' => [
						'addIfMissing' => false,
						'addLanguageTemplate' => true,
						'sourceParameters' => 'source_param',
					] ],
				] ],
			],
			'%MAGIC_WORD% syntax' => [
				'wikiText' => ";Source:Target|target_param=%MAGIC_WORD%",
				'expected' => [ 'Source' => [
					'targetTemplate' => 'Target',
					'parameters' => [],
				] ],
			],

			// TODO: Test + and @ when not the first character
			// TODO: Test combinations of + and @ with a %MAGIC_WORD%
			// TODO: Test %MAGIC_WORD% syntax when surrounded by text
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
