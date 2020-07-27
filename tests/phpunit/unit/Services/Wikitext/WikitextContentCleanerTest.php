<?php

namespace FileImporter\Tests\Services\Wikitext;

use FileImporter\Data\WikitextConversions;
use FileImporter\Services\Wikitext\WikitextContentCleaner;

/**
 * @covers \FileImporter\Services\Wikitext\WikitextContentCleaner
 *
 * @license GPL-2.0-or-later
 * @author Thiemo Kreuz
 */
class WikitextContentCleanerTest extends \MediaWikiUnitTestCase {

	public function provideTemplateRemovals() {
		return [
			'empty' => [
				'removals' => [],
				'wikitext' => '{{movetocommons}}',
				'expectedWikitext' => '{{movetocommons}}',
				'expectedCount' => 0,
			],

			// TODO: Do we need to test substring matching, triple brackets, unclosed templates, and
			// other parser features again (@see provideTemplateReplacements below)?

			'basic, case-insensitive removal' => [
				'removals' => [ 'MoveToCommons' ],
				'wikitext' => "before\n\n{{movetocommons}}\n\nafter",
				'expectedWikitext' => "before\n\nafter",
			],

			'remove parameters and nested templates' => [
				'removals' => [ 'a' ],
				'wikitext' => '{{before}}{{a |p1={{b |p1=… }} |p2=… }}{{after}}',
				'expectedWikitext' => '{{before}}{{after}}',
			],

			'end-of-text' => [
				'removals' => [ 'Info' ],
				'wikitext' => "Before\n{{Info|a=|b=",
				'expectedWikitext' => 'Before',
			],

			'more than 2 opening brackets' => [
				'removals' => [ 'second' ],
				'wikitext' => '{{first|{{second|{{{third|a}}|b}}|c}}',
				'expectedWikitext' => '{{first||c}}',
			],
			'more than 2 closing brackets' => [
				'removals' => [ 'second' ],
				'wikitext' => '{{first|{{second|b}}|c}}',
				'expectedWikitext' => '{{first||c}}',
			],
		];
	}

	/**
	 * @dataProvider provideTemplateRemovals
	 */
	public function testTemplateRemovals(
		array $removals,
		$wikitext,
		$expectedWikitext,
		$expectedCount = 1
	) {
		$conversions = new WikitextConversions( [
			WikitextConversions::OBSOLETE_TEMPLATES => $removals,
		] );
		$cleaner = new WikitextContentCleaner( $conversions );

		$this->assertSame( $expectedWikitext, $cleaner->cleanWikitext( $wikitext ) );
		$this->assertSame( $expectedCount, $cleaner->getLatestNumberOfReplacements() );
	}

	public function provideTemplateReplacements() {
		return [
			'nothing to do' => [
				'replacements' => [ 'here' => 'there' ],
				'wikitext' => 'nothing to do here',
				'expectedWikitext' => null,
				'expectedCount' => 0,
			],

			'no substring matching' => [
				'replacements' => [ 'a' => 'b' ],
				'wikitext' => '{{ax}}{{ax|}}{{xa}}{{xa|}}',
				'expectedWikitext' => null,
				'expectedCount' => 0,
			],

			'skip triple bracket parameter syntax' => [
				'replacements' => [ 'a' => 'b' ],
				'wikitext' => '{{{a}}}',
				'expectedWikitext' => null,
				'expectedCount' => 0,
			],

			'skip unclosed templates' => [
				'replacements' => [ 'a' => 'b' ],
				'wikitext' => '{{a}',
				'expectedWikitext' => null,
				'expectedCount' => 0,
			],

			'most trivial match' => [
				'replacements' => [ 'Info' => 'Information' ],
				'wikitext' => '{{Info}}',
				'expectedWikitext' => '{{Information}}',
			],

			'case-insensitive' => [
				'replacements' => [ 'Info' => 'Information' ],
				'wikitext' => '{{info}}',
				'expectedWikitext' => '{{Information}}',
			],

			'complex parameters' => [
				'replacements' => [ 'Info' => 'Information' ],
				'wikitext' => '{{info|desc={{en|Desc with a [[…|link]].}}}}',
				'expectedWikitext' => '{{Information|desc={{en|Desc with a [[…|link]].}}}}',
			],

			'count different replacements' => [
				'replacements' => [ 'a' => 'b', 'x' => 'y' ],
				'wikitext' => '{{a}}{{x|some params}}',
				'expectedWikitext' => '{{b}}{{y|some params}}',
				'expectedCount' => 2,
			],

			'count identical replacements' => [
				'replacements' => [ 'a' => 'b' ],
				'wikitext' => '{{a}}{{a|some params}}',
				'expectedWikitext' => '{{b}}{{b|some params}}',
				'expectedCount' => 2,
			],

			'keep multi-line syntax' => [
				'replacements' => [ 'Info' => 'Information' ],
				'wikitext' => "{{Info\n| param = value\n}}",
				'expectedWikitext' => "{{Information\n| param = value\n}}",
			],

			'replace nested templates' => [
				'replacements' => [ 'Info' => 'Information', 'enS' => 'en' ],
				'wikitext' => '{{Info|{{enS|…}}}}',
				'expectedWikitext' => '{{Information|{{en|…}}}}',
				'expectedCount' => 2,
			],

			'most trivial parameter replacement' => [
				'replacements' => [ 'Info' => [
					'targetTemplate' => 'Info',
					'parameters' => [ 'Description' => [ 'sourceParameters' => 'Desc' ] ],
				] ],
				'wikitext' => '{{Info|Desc=…}}',
				'expectedWikitext' => '{{Info|Description=…}}',
			],

			'nested templates do not shift parameter offsets' => [
				'replacements' => [
					'a' => [
						'targetTemplate' => 'a',
						'parameters' => [ 'parameter2' => [ 'sourceParameters' => 'p2' ] ],
					],
					'b' => [
						'targetTemplate' => 'b',
						'parameters' => [ 'parameter1' => [ 'sourceParameters' => 'p1' ] ],
					],
				],
				'wikitext' => '{{a |p1={{b |p1=… }} |p2=… }}',
				'expectedWikitext' => '{{a |p1={{b |parameter1=… }} |parameter2=… }}',
				'expectedCount' => 2,
			],

			'replace numeric parameters' => [
				'replacements' => [ 'Info' => [
					'targetTemplate' => 'Info',
					'parameters' => [ 'Description' => [ 'sourceParameters' => 2 ] ],
				] ],
				'wikitext' => '{{Info|a|b}}',
				'expectedWikitext' => '{{Info|a|Description=b}}',
			],

			'multiple source parameters' => [
				'replacements' => [ 'Info' => [
					'targetTemplate' => 'Info',
					'parameters' => [ 'Description' => [ 'sourceParameters' => [ 1, 'desc', 'Desc' ] ] ],
				] ],
				'wikitext' => '{{Info|1}}
					{{Info|desc=lower}}
					{{Info|Desc=upper}}
					{{Info|1|desc=lower|Desc=upper|2}}
					{{Info|Desc=upper|desc=lower|1|2}}',
				'expectedWikitext' => '{{Info|Description=1}}
					{{Info|Description=lower}}
					{{Info|Description=upper}}
					{{Info|Description=1|Description=lower|Description=upper|2}}
					{{Info|Description=upper|Description=lower|Description=1|2}}',
				'expectedCount' => 5,
			],

			'add language template' => [
				'replacements' => [ 'Info' => [
					'targetTemplate' => 'Info',
					'parameters' => [ 'desc' => [
						'sourceParameters' => 'desc',
						'addLanguageTemplate' => true
					] ],
				] ],
				'wikitext' => "{{Info|desc = foo [[…|x=1]] \n|desc = \n}}",
				'expectedWikitext' => "{{Info|desc = {{de|foo [[…|x=1]]}} \n|desc = {{de|}}\n}}",
			],

			'add language template to unnamed parameter' => [
				'replacements' => [ 'Info' => [
					'targetTemplate' => 'Info',
					'parameters' => [ 'desc' => [
						'sourceParameters' => 1,
						'addLanguageTemplate' => true
					] ],
				] ],
				'wikitext' => '{{Info| foo [[…|x]] }}',
				'expectedWikitext' => '{{Info|desc= {{de|foo [[…|x]]}} }}',
			],

			'add missing parameter' => [
				'replacements' => [ 'Bild-GFDL-Neu' => [
					'targetTemplate' => 'GFDL',
					'parameters' => [ 'migration' => [
						'addIfMissing' => true,
						'value' => 'not-eligible',
					] ],
				] ],
				'wikitext' => '{{Bild-GFDL-Neu}}',
				'expectedWikitext' => '{{GFDL|migration=not-eligible}}',
			],

			'add missing parameter reuses existing wikitext format' => [
				'replacements' => [ 'Bild-GFDL-Neu' => [
					'targetTemplate' => 'GFDL',
					'parameters' => [ 'migration' => [ 'addIfMissing' => true ] ],
				] ],
				'wikitext' => "{{Bild-GFDL-Neu\n | p1 = …\n}}",
				'expectedWikitext' => "{{GFDL\n | migration = \n | p1 = …\n}}",
			],

			'reusing wikitext format with unnamed parameters' => [
				'replacements' => [ 'a' => [
					'targetTemplate' => 'a',
					'parameters' => [ 'p1' => [ 'addIfMissing' => true ] ],
				] ],
				'wikitext' => '{{a | …}}',
				'expectedWikitext' => '{{a | p1= | …}}',
			],

			'add missing parameter without value' => [
				'replacements' => [ 'Bild-GFDL-Neu' => [
					'targetTemplate' => 'GFDL',
					'parameters' => [ 'migration' => [ 'addIfMissing' => true ] ],
				] ],
				'wikitext' => "{{Bild-GFDL-Neu\n}}",
				'expectedWikitext' => "{{GFDL|migration=\n}}",
			],

			'add missing parameter cannot replace existing' => [
				'replacements' => [ 'Bild-GFDL-Neu' => [
					'targetTemplate' => 'GFDL',
					'parameters' => [ 'migration' => [
						'addIfMissing' => true,
						'value' => 'new',
					] ],
				] ],
				'wikitext' => '{{Bild-GFDL-Neu|migration=old}}',
				'expectedWikitext' => '{{GFDL|migration=old}}',
			],

			// TODO: "value" should overwrite empty old value
			// TODO: "value" should not overwrite non-empty old value
			// TODO: Test combinations of "sourceParameters" and "addIfMissing" with "value"

			'must skip single brackets' => [
				'replacements' => [ 'Info' => [
					'targetTemplate' => 'Info',
					'parameters' => [ 'Description' => [ 'sourceParameters' => 'Desc' ] ],
				] ],
				'wikitext' => '{{Info|}|Desc=…}}{{Info|{|Desc=…}}',
				'expectedWikitext' => '{{Info|}|Description=…}}{{Info|{|Description=…}}',
				'expectedCount' => 2,
			],

			'must skip {{{…}}} syntax' => [
				'replacements' => [ 'Info' => [
					'targetTemplate' => 'Info',
					'parameters' => [ 'Description' => [ 'sourceParameters' => 'Desc' ] ],
				] ],
				'wikitext' => '{{Info|{{{Info|Desc=…}}}|Desc=…}}',
				'expectedWikitext' => '{{Info|{{{Info|Desc=…}}}|Description=…}}',
			],

			'previously misdetected nested …}} as end of parameter' => [
				'replacements' => [ 'Info' => [
					'targetTemplate' => 'Info',
					'parameters' => [
						'x' => [
							'sourceParameters' => 'a',
							'addLanguageTemplate' => true
						],
					],
				] ],
				'wikitext' => '{{Info|a={{q}}more }',
				'expectedWikitext' => '{{Info|x={{de|{{q}}more}} }',
			],

			'end-of-text' => [
				'replacements' => [ 'Info' => [
					'targetTemplate' => 'Info',
					'parameters' => [
						'x' => [ 'sourceParameters' => 'a' ],
						'y' => [ 'sourceParameters' => 'b' ],
					],
				] ],
				'wikitext' => '{{Info|a=|b=[[]',
				'expectedWikitext' => '{{Info|x=|y=[[]',
			],
		];
	}

	/**
	 * @dataProvider provideTemplateReplacements
	 */
	public function testTemplateReplacements(
		array $replacements,
		$wikitext,
		$expectedWikitext,
		$expectedCount = 1
	) {
		$conversions = new WikitextConversions( [], [], [], [], $replacements );
		$cleaner = new WikitextContentCleaner( $conversions );
		$cleaner->setSourceWikiLanguageTemplate( 'de' );

		$this->assertSame( $expectedWikitext ?: $wikitext, $cleaner->cleanWikitext( $wikitext ) );
		$this->assertSame( $expectedCount, $cleaner->getLatestNumberOfReplacements() );
	}

	public function provideHeadingReplacements() {
		return [
			[ '==Description==', '=={{int:filedesc}}==' ],
			[ '==Licensing==', '=={{int:license-header}}==' ],
			[ "==Description==\n==Licensing==", "=={{int:filedesc}}==\n=={{int:license-header}}==" ],
			[ '= Description =', '= {{int:filedesc}} =' ],
			[ "===Description=== \n Code", "==={{int:filedesc}}=== \n Code" ],
			[ '==兵庫県立考古博物館==', '==Japan==' ],

			[ '==Description=', '==Description=' ],
			[ '=Description==', '=Description==' ],
		];
	}

	/**
	 * @dataProvider provideHeadingReplacements
	 */
	public function testHeadingReplacements( $wikitext, $expectedWikitext ) {
		$conversions = new WikitextConversions( [ WikitextConversions::HEADING_REPLACEMENTS => [
			'Description' => '{{int:filedesc}}',
			'Licensing' => '{{int:license-header}}',
			'兵庫県立考古博物館' => 'Japan',
		] ] );
		$cleaner = new WikitextContentCleaner( $conversions );

		$this->assertSame( $expectedWikitext, $cleaner->cleanWikitext( $wikitext ) );
		$this->assertSame( 0, $cleaner->getLatestNumberOfReplacements() );
	}

}
