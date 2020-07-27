<?php

namespace FileImporter\Tests\Services\Wikitext;

use FileImporter\Data\WikitextConversions;
use FileImporter\Exceptions\LocalizedImportException;
use FileImporter\Services\Wikitext\CommonsHelperConfigParser;

/**
 * @covers \FileImporter\Services\Wikitext\CommonsHelperConfigParser
 *
 * @license GPL-2.0-or-later
 * @author Thiemo Kreuz
 */
class CommonsHelperConfigParserTest extends \PHPUnit\Framework\TestCase {

	public function provideCommonsHelperConfig() {
		return [
			'empty' => [
				'wikitext' => '',
				'expectedGoodTemplates' => null,
				'expectedBadTemplates' => null,
				'expectedBadCategories' => null,
				'expectedObsoleteTemplates' => null,
				'expectedException' => '"Categories"'
			],

			'missing "bad templates" heading' => [
				'wikitext' => "== Categories ==\n=== Bad ===\n" .
					"== Templates ==\n=== Good ===\n" .
					'== Information ==',
				'expectedGoodTemplates' => null,
				'expectedBadTemplates' => null,
				'expectedBadCategories' => null,
				'expectedObsoleteTemplates' => null,
				'expectedException' => '"Templates/Bad"'
			],

			'missing "good templates" heading' => [
				'wikitext' => "== Categories ==\n=== Bad ===\n" .
					"== Templates ==\n== Information ==",
				'expectedGoodTemplates' => null,
				'expectedBadTemplates' => null,
				'expectedBadCategories' => null,
				'expectedObsoleteTemplates' => null,
				'expectedException' => '"Templates/Good"'
			],

			'missing "remove templates" heading' => [
				'wikitext' => "== Categories ==\n=== Bad ===\n" .
					"== Templates ==\n=== Good ===\n=== Bad ===\n== Information ==",
				'expectedGoodTemplates' => null,
				'expectedBadTemplates' => null,
				'expectedBadCategories' => null,
				'expectedObsoleteTemplates' => null,
				'expectedException' => '"Templates/Remove"'
			],

			'missing "transfer templates" heading' => [
				'wikitext' => "== Categories ==\n=== Bad ===\n" .
					"== Templates ==\n=== Good ===\n=== Bad ===\n=== Remove ===\n== Information ==",
				'expectedGoodTemplates' => null,
				'expectedBadTemplates' => null,
				'expectedBadCategories' => null,
				'expectedObsoleteTemplates' => null,
				'expectedException' => '"Templates/Transfer"'
			],

			'missing "bad categories" heading' => [
				'wikitext' => "== Categories ==\n== Templates ==\n== Information ==",
				'expectedGoodTemplates' => null,
				'expectedBadTemplates' => null,
				'expectedBadCategories' => null,
				'expectedObsoleteTemplates' => null,
				'expectedException' => '"Categories/Bad"'
			],

			'missing "information" heading' => [
				'wikitext' => "== Categories ==\n== Templates ==",
				'expectedGoodTemplates' => null,
				'expectedBadTemplates' => null,
				'expectedBadCategories' => null,
				'expectedObsoleteTemplates' => null,
				'expectedException' => '"Information"'
			],

			'missing "description" heading' => [
				'wikitext' => "== Categories ==\n=== Bad ===\n" .
					"== Templates ==\n=== Good ===\n=== Bad ===\n=== Remove ===\n" .
					"=== Transfer ===\n== Information ==",
				'expectedGoodTemplates' => null,
				'expectedBadTemplates' => null,
				'expectedBadCategories' => null,
				'expectedObsoleteTemplates' => null,
				'expectedException' => '"Information/Description"'
			],

			'missing "licensing" heading' => [
				'wikitext' => "== Categories ==\n=== Bad ===\n" .
					"== Templates ==\n=== Good ===\n=== Bad ===\n=== Remove ===\n" .
					"=== Transfer ===\n== Information ==\n=== Description ===",
				'expectedGoodTemplates' => null,
				'expectedBadTemplates' => null,
				'expectedBadCategories' => null,
				'expectedObsoleteTemplates' => null,
				'expectedException' => '"Information/Licensing"'
			],

			'missing lists' => [
				'wikitext' => "== Categories ==\n=== Bad ===\n" .
					"== Templates ==\n=== Good ===\n=== Bad ===\n=== Remove ===\n" .
					"=== Transfer ===\n== Information ==\n=== Description ===\n" .
					"=== Licensing ===\n",
				'expectedGoodTemplates' => [],
				'expectedBadTemplates' => [],
				'expectedBadCategories' => [],
				'expectedObsoleteTemplates' => [],
			],

			'empty lists' => [
				'wikitext' => <<<WIKITEXT
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
				'wikitext' => <<<WIKITEXT
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
* 兵庫県立考古博物館
== Information ==
=== Description ===
=== Licensing ===
WIKITEXT
				,
				'expectedGoodTemplates' => [ 'GoodTemplate' ],
				'expectedBadTemplates' => [ 'BadTemplate' ],
				'expectedBadCategories' => [ '兵庫県立考古博物館' ],
				'expectedObsoleteTemplates' => [ 'ObsoleteTemplate' ],
			],

			'compressed syntax' => [
				'wikitext' => <<<WIKITEXT
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
				'wikitext' => <<<WIKITEXT
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
				'wikitext' => <<<WIKITEXT
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
		$wikitext,
		array $expectedGoodTemplates = null,
		array $expectedBadTemplates = null,
		array $expectedBadCategories = null,
		array $expectedObsoleteTemplates = null,
		$expectedException = null
	) {
		$parser = new CommonsHelperConfigParser( '', $wikitext );

		if ( $expectedException ) {
			$this->expectException( LocalizedImportException::class );
			$this->expectExceptionMessage( $expectedException );
		}
		$conversions = $parser->getWikitextConversions();

		$expected = new WikitextConversions(
			$expectedGoodTemplates,
			$expectedBadTemplates,
			$expectedBadCategories,
			$expectedObsoleteTemplates,
			[]
		);
		$this->assertEquals( $expected, $conversions );
	}

	public function testHeadingReplacements() {
		$wikitext = "== Categories ==\n=== Bad ===\n" .
			"== Templates ==\n=== Good ===\n=== Bad ===\n=== Remove ===\n=== Transfer ===\n" .
			"== Information ==\n=== Description ===\n* A\n=== Licensing ===\n* 兵庫県立考古博物館";
		$parser = new CommonsHelperConfigParser( '', $wikitext );

		$expected = new WikitextConversions( [ WikitextConversions::HEADING_REPLACEMENTS => [
			'A' => '{{int:filedesc}}',
			'兵庫県立考古博物館' => '{{int:license-header}}',
		] ] );
		$this->assertEquals( $expected, $parser->getWikitextConversions() );
	}

	public function provideTransferRules() {
		return [
			'empty' => [
				'wikitext' => '',
				'expected' => [],
			],
			'empty <dt> element' => [
				'wikitext' => ';',
				'expected' => [],
			],
			'no <dd> element' => [
				'wikitext' => ';Source',
				'expected' => [],
			],
			'empty <dd> element' => [
				'wikitext' => ';Source:',
				'expected' => [],
			],
			'empty <dd> element on next line' => [
				'wikitext' => ";Source\n:",
				'expected' => [],
			],
			'to many newlines' => [
				'wikitext' => ";Source\n\n:Target",
				'expected' => [],
			],
			'incomplete parameter syntax' => [
				'wikitext' => ';Source:Target|incomplete',
				'expected' => [ 'Source' => 'Target' ],
			],
			'bad parameter syntax on local side' => [
				'wikitext' => ';Source|param:Target',
				'expected' => [],
			],
			'subst' => [
				'wikitext' => ';Source:subst:Target',
				'expected' => [ 'Source' => 'subst:Target' ],
			],
			'Unicode' => [
				'wikitext' => ';兵庫県立考古博物館:兵庫県立考古博物館',
				'expected' => [ '兵庫県立考古博物館' => '兵庫県立考古博物館' ],
			],

			'basic 1-line syntax' => [
				'wikitext' => ';Source:Target',
				'expected' => [ 'Source' => 'Target' ],
			],
			'basic 2-line syntax' => [
				'wikitext' => ";Source\n:Target",
				'expected' => [ 'Source' => 'Target' ],
			],
			'empty parameter list' => [
				'wikitext' => ';Source:Target|',
				'expected' => [ 'Source' => 'Target' ],
			],

			'one basic parameter' => [
				'wikitext' => ';Source:Target|target_param=source_param',
				'expected' => [ 'Source' => [
					'targetTemplate' => 'Target',
					'parameters' => [ 'target_param' => [
						'addIfMissing' => false,
						'addLanguageTemplate' => false,
						'sourceParameters' => [ 'source_param' ],
					] ],
				] ],
			],
			'additional whitespace' => [
				'wikitext' => '; Source : Target | target_param = source_param',
				'expected' => [ 'Source' => [
					'targetTemplate' => 'Target',
					'parameters' => [ 'target_param' => [
						'addIfMissing' => false,
						'addLanguageTemplate' => false,
						'sourceParameters' => [ 'source_param' ],
					] ],
				] ],
			],
			'+add syntax' => [
				'wikitext' => ';Source:Target|+target_param=static value',
				'expected' => [ 'Source' => [
					'targetTemplate' => 'Target',
					'parameters' => [ 'target_param' => [
						'addIfMissing' => true,
						'addLanguageTemplate' => false,
						'value' => 'static value',
					] ],
				] ],
			],
			'@language parameter syntax' => [
				'wikitext' => ';Source:Target|@target_param=source_param',
				'expected' => [ 'Source' => [
					'targetTemplate' => 'Target',
					'parameters' => [ 'target_param' => [
						'addIfMissing' => false,
						'addLanguageTemplate' => true,
						'sourceParameters' => [ 'source_param' ],
					] ],
				] ],
			],
			'+@ combination leaves a meaningless @ behind' => [
				'wikitext' => ';Source:Target|+@target_param=static value',
				'expected' => [ 'Source' => [
					'targetTemplate' => 'Target',
					'parameters' => [ '@target_param' => [
						'addIfMissing' => true,
						'addLanguageTemplate' => false,
						'value' => 'static value',
					] ],
				] ],
			],
			'@+ combination leaves a meaningless + behind' => [
				'wikitext' => ';Source:Target|@+target_param=source_param',
				'expected' => [ 'Source' => [
					'targetTemplate' => 'Target',
					'parameters' => [ '+target_param' => [
						'addIfMissing' => false,
						'addLanguageTemplate' => true,
						'sourceParameters' => [ 'source_param' ],
					] ],
				] ],
			],
			'%MAGIC_WORD% syntax' => [
				'wikitext' => ';Source:Target|target_param=%MAGIC_WORD%',
				'expected' => [ 'Source' => [
					'targetTemplate' => 'Target',
					'parameters' => [],
				] ],
			],

			'parameter aliases' => [
				'wikiText' => ';Source:Target|@target_param=source_param1|target_param=source_param2',
				'expected' => [ 'Source' => [
					'targetTemplate' => 'Target',
					'parameters' => [ 'target_param' => [
						'addIfMissing' => false,
						'addLanguageTemplate' => true,
						'sourceParameters' => [ 'source_param1', 'source_param2' ],
					] ],
				] ],
			],
			'default value' => [
				'wikiText' => ';Source:Target|target_param=source_param|+target_param=static value',
				'expected' => [ 'Source' => [
					'targetTemplate' => 'Target',
					'parameters' => [ 'target_param' => [
						'addIfMissing' => true,
						'addLanguageTemplate' => false,
						'sourceParameters' => [ 'source_param' ],
						'value' => 'static value',
					] ],
				] ],
			],
		];
	}

	/**
	 * @dataProvider provideTransferRules
	 */
	public function testTransferRules( $wikitext, array $expected ) {
		$wikitext = "== Categories ==\n=== Bad ===\n" .
			"== Templates ==\n=== Good ===\n=== Bad ===\n=== Remove ===\n=== Transfer ===\n" .
			"$wikitext\n== Information ==\n=== Description ===\n=== Licensing ===";
		$parser = new CommonsHelperConfigParser( '', $wikitext );

		$expected = new WikitextConversions( [], [], [], [], $expected );
		$this->assertEquals( $expected, $parser->getWikitextConversions() );
	}

}
