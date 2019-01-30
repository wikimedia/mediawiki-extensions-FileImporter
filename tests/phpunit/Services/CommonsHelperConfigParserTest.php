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
				'expectedObsoleteTemplates' => null,
				'expectedException' => '"Categories"'
			],

			'missing "bad templates" heading' => [
				'wikiText' => "== Categories ==\n=== Bad ===\n" .
					"== Templates ==\n=== Good ===\n" .
					"== Information ==",
				'expectedGoodTemplates' => null,
				'expectedBadTemplates' => null,
				'expectedBadCategories' => null,
				'expectedObsoleteTemplates' => null,
				'expectedException' => '"Templates/Bad"'
			],

			'missing "good templates" heading' => [
				'wikiText' => "== Categories ==\n=== Bad ===\n" .
					"== Templates ==\n== Information ==",
				'expectedGoodTemplates' => null,
				'expectedBadTemplates' => null,
				'expectedBadCategories' => null,
				'expectedObsoleteTemplates' => null,
				'expectedException' => '"Templates/Good"'
			],

			'missing "remove templates" heading' => [
				'wikiText' => "== Categories ==\n=== Bad ===\n" .
					"== Templates ==\n=== Good ===\n=== Bad ===\n== Information ==",
				'expectedGoodTemplates' => null,
				'expectedBadTemplates' => null,
				'expectedBadCategories' => null,
				'expectedObsoleteTemplates' => null,
				'expectedException' => '"Templates/Remove"'
			],

			'missing "transfer templates" heading' => [
				'wikiText' => "== Categories ==\n=== Bad ===\n" .
					"== Templates ==\n=== Good ===\n=== Bad ===\n=== Remove ===\n== Information ==",
				'expectedGoodTemplates' => null,
				'expectedBadTemplates' => null,
				'expectedBadCategories' => null,
				'expectedObsoleteTemplates' => null,
				'expectedException' => '"Templates/Transfer"'
			],

			'missing "bad categories" heading' => [
				'wikiText' => "== Categories ==\n== Templates ==\n== Information ==",
				'expectedGoodTemplates' => null,
				'expectedBadTemplates' => null,
				'expectedBadCategories' => null,
				'expectedObsoleteTemplates' => null,
				'expectedException' => '"Categories/Bad"'
			],

			'missing "information" heading' => [
				'wikiText' => "== Categories ==\n== Templates ==",
				'expectedGoodTemplates' => null,
				'expectedBadTemplates' => null,
				'expectedBadCategories' => null,
				'expectedObsoleteTemplates' => null,
				'expectedException' => '"Information"'
			],

			'missing "description" heading' => [
				'wikiText' => "== Categories ==\n=== Bad ===\n" .
					"== Templates ==\n=== Good ===\n=== Bad ===\n=== Remove ===\n" .
					"=== Transfer ===\n== Information ==",
				'expectedGoodTemplates' => null,
				'expectedBadTemplates' => null,
				'expectedBadCategories' => null,
				'expectedObsoleteTemplates' => null,
				'expectedException' => '"Information/Description"'
			],

			'missing "licensing" heading' => [
				'wikiText' => "== Categories ==\n=== Bad ===\n" .
					"== Templates ==\n=== Good ===\n=== Bad ===\n=== Remove ===\n" .
					"=== Transfer ===\n== Information ==\n=== Description ===",
				'expectedGoodTemplates' => null,
				'expectedBadTemplates' => null,
				'expectedBadCategories' => null,
				'expectedObsoleteTemplates' => null,
				'expectedException' => '"Information/Licensing"'
			],

			'missing lists' => [
				'wikiText' => "== Categories ==\n=== Bad ===\n" .
					"== Templates ==\n=== Good ===\n=== Bad ===\n=== Remove ===\n" .
					"=== Transfer ===\n== Information ==\n=== Description ===\n" .
					"=== Licensing ===\n",
				'expectedGoodTemplates' => [],
				'expectedBadTemplates' => [],
				'expectedBadCategories' => [],
				'expectedObsoleteTemplates' => [],
			],

			'empty lists' => [
				'wikiText' => <<<WIKITEXT
== Templates ==
=== Good ===
*
=== Bad ===
*
=== Remove ===
=== Transfer ===
== Categories ==
=== Bad ===
*
== Information ==
=== Description ===
=== Licensing ===
WIKITEXT
				,
				'expectedGoodTemplates' => [],
				'expectedBadTemplates' => [],
				'expectedBadCategories' => [],
				'expectedObsoleteTemplates' => [],
			],

			'simple 1-element lists' => [
				'wikiText' => <<<WIKITEXT
== Templates ==
=== Good ===
* GoodTemplate
=== Bad ===
* BadTemplate
=== Remove ===
* ObsoleteTemplate
=== Transfer ===
== Categories ==
=== Bad ===
* BadCategory
== Information ==
=== Description ===
=== Licensing ===
WIKITEXT
				,
				'expectedGoodTemplates' => [ 'GoodTemplate' ],
				'expectedBadTemplates' => [ 'BadTemplate' ],
				'expectedBadCategories' => [ 'BadCategory' ],
				'expectedObsoleteTemplates' => [ 'ObsoleteTemplate' ],
			],

			'compressed syntax' => [
				'wikiText' => <<<WIKITEXT
==Templates==
===Good===
* GoodTemplate
===Bad===
*BadTemplate
===Remove===
*ObsoleteTemplate
===Transfer===
==Categories==
===Bad===
*BadCategory
==Information==
===Description===
===Licensing===
WIKITEXT
				,
				'expectedGoodTemplates' => [ 'GoodTemplate' ],
				'expectedBadTemplates' => [ 'BadTemplate' ],
				'expectedBadCategories' => [ 'BadCategory' ],
				'expectedObsoleteTemplates' => [ 'ObsoleteTemplate' ],
			],

			'tabs' => [
				'wikiText' => <<<WIKITEXT
==	Templates	==\t

===	Good	===\t

*	GoodTemplate\t

===	Bad	===\t

*	BadTemplate\t

=== Remove ===

*	ObsoleteTemplate\t
=== Transfer ===
==	Categories	==\t

===	Bad	===\t

*	BadCategory\t
== Information ==
=== Description ===
=== Licensing ===
WIKITEXT
				,
				'expectedGoodTemplates' => [ 'GoodTemplate' ],
				'expectedBadTemplates' => [ 'BadTemplate' ],
				'expectedBadCategories' => [ 'BadCategory' ],
				'expectedObsoleteTemplates' => [ 'ObsoleteTemplate' ],
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
=== Remove ===
*<!-- Comment -->\n
* ObsoleteTemplate
*	<!-- Comment -->	
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
== Information ==
=== Description ===
=== Licensing ===
WIKITEXT
				,
				'expectedGoodTemplates' => [ 'GoodTemplate' ],
				'expectedBadTemplates' => [ 'BadTemplate' ],
				'expectedBadCategories' => [ 'BadCategory' ],
				'expectedObsoleteTemplates' => [ 'ObsoleteTemplate' ],
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
		array $expectedObsoleteTemplates = null,
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
				$expectedObsoleteTemplates,
				[]
			);
			$this->assertEquals( $expected, $parser->getWikiTextConversions() );
		}
	}

	public function testHeadingReplacements() {
		$wikiText = "== Categories ==\n=== Bad ===\n" .
			"== Templates ==\n=== Good ===\n=== Bad ===\n=== Remove ===\n=== Transfer ===\n" .
			"== Information ==\n=== Description ===\n* A\n=== Licensing ===\n* B";
		$parser = new CommonsHelperConfigParser( '', $wikiText );

		$expected = new WikiTextConversions( [], [], [], [], [] );
		$expected->setHeadingReplacements( [
			'A' => '{{int:filedesc}}',
			'B' => '{{int:license-header}}',
		] );
		$this->assertEquals( $expected, $parser->getWikiTextConversions() );
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
			'incomplete parameter syntax' => [
				'wikiText' => ";Source:Target|incomplete",
				'expected' => [ 'Source' => 'Target' ],
			],
			'bad parameter syntax on local side' => [
				'wikiText' => ";Source|param:Target",
				'expected' => [],
			],
			'subst' => [
				'wikiText' => ";Source:subst:Target",
				'expected' => [ 'Source' => 'subst' ],
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
		$wikiText = "== Categories ==\n=== Bad ===\n" .
			"== Templates ==\n=== Good ===\n=== Bad ===\n=== Remove ===\n=== Transfer ===\n" .
			"$wikiText\n== Information ==\n=== Description ===\n=== Licensing ===";
		$parser = new CommonsHelperConfigParser( '', $wikiText );

		$expected = new WikiTextConversions( [], [], [], [], $expected );
		$this->assertEquals( $expected, $parser->getWikiTextConversions() );
	}

}
