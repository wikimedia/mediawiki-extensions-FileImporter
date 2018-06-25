<?php

namespace FileImporter\Tests\Services;

use FileImporter\Data\WikiTextConversions;
use FileImporter\Services\WikiTextContentCleaner;

/**
 * @covers \FileImporter\Services\WikiTextContentCleaner
 */
class WikiTextContentCleanerTest extends \PHPUnit\Framework\TestCase {
	use \PHPUnit4And6Compat;

	public function provideTemplateReplacements() {
		return [
			'nothing to do' => [
				'replacements' => [ 'here' => 'there' ],
				'wikitext' => 'nothing to do here',
				'expectedWikiText' => null,
				'expectedCount' => 0,
			],

			'no substring matching' => [
				'replacements' => [ 'a' => 'b' ],
				'wikitext' => '{{ax}}{{ax|}}{{xa}}{{xa|}}',
				'expectedWikiText' => null,
				'expectedCount' => 0,
			],

			'skip triple bracket parameter syntax' => [
				'replacements' => [ 'a' => 'b' ],
				'wikitext' => '{{{a}}}',
				'expectedWikiText' => null,
				'expectedCount' => 0,
			],

			'skip unclosed templates' => [
				'replacements' => [ 'a' => 'b' ],
				'wikitext' => '{{a}',
				'expectedWikiText' => null,
				'expectedCount' => 0,
			],

			'most trivial match' => [
				'replacements' => [ 'Info' => 'Information' ],
				'wikitext' => '{{Info}}',
				'expectedWikiText' => '{{Information}}',
				'expectedCount' => 1,
			],

			'case-insensitive' => [
				'replacements' => [ 'Info' => 'Information' ],
				'wikitext' => '{{info}}',
				'expectedWikiText' => '{{Information}}',
				'expectedCount' => 1,
			],

			'complex parameters' => [
				'replacements' => [ 'Info' => 'Information' ],
				'wikitext' => '{{info|desc={{en|Desc with a [[…|link]].}}}}',
				'expectedWikiText' => '{{Information|desc={{en|Desc with a [[…|link]].}}}}',
				'expectedCount' => 1,
			],

			'count different replacements' => [
				'replacements' => [ 'a' => 'b', 'x' => 'y' ],
				'wikitext' => '{{a}}{{x|some params}}',
				'expectedWikiText' => '{{b}}{{y|some params}}',
				'expectedCount' => 2,
			],

			'count identical replacements' => [
				'replacements' => [ 'a' => 'b' ],
				'wikitext' => '{{a}}{{a|some params}}',
				'expectedWikiText' => '{{b}}{{b|some params}}',
				'expectedCount' => 2,
			],

			'keep multi-line syntax' => [
				'replacements' => [ 'Info' => 'Information' ],
				'wikitext' => "{{Info\n| param = value\n}}",
				'expectedWikiText' => "{{Information\n| param = value\n}}",
				'expectedCount' => 1,
			],

			'replace nested templates' => [
				'replacements' => [ 'Info' => 'Information', 'enS' => 'en' ],
				'wikitext' => '{{Info|{{enS|…}}}}',
				'expectedWikiText' => '{{Information|{{en|…}}}}',
				'expectedCount' => 2,
			],

			'most trivial parameter replacement' => [
				'replacements' => [ 'Info' => [
					'commonsTemplate' => 'Info',
					'parameters' => [ 'Description' => [ 'localParameters' => 'Desc' ] ],
				] ],
				'wikitext' => '{{Info|Desc=…}}',
				'expectedWikiText' => '{{Info|Description=…}}',
				'expectedCount' => 1,
			],

			'replace numeric parameters' => [
				'replacements' => [ 'Info' => [
					'commonsTemplate' => 'Info',
					'parameters' => [ 'Description' => [ 'localParameters' => 2 ] ],
				] ],
				'wikitext' => '{{Info|a|b}}',
				'expectedWikiText' => '{{Info|a|Description=b}}',
				'expectedCount' => 1,
			],

			'must skip single brackets' => [
				'replacements' => [ 'Info' => [
					'commonsTemplate' => 'Info',
					'parameters' => [ 'Description' => [ 'localParameters' => 'Desc' ] ],
				] ],
				'wikitext' => '{{Info|}|Desc=…}}{{Info|{|Desc=…}}',
				'expectedWikiText' => '{{Info|}|Description=…}}{{Info|{|Description=…}}',
				'expectedCount' => 2,
			],

			'must skip {{{…}}} syntax' => [
				'replacements' => [ 'Info' => [
					'commonsTemplate' => 'Info',
					'parameters' => [ 'Description' => [ 'localParameters' => 'Desc' ] ],
				] ],
				'wikitext' => '{{Info|{{{Info|Desc=…}}}|Desc=…}}',
				'expectedWikiText' => '{{Info|{{{Info|Desc=…}}}|Description=…}}',
				'expectedCount' => 1,
			],
		];
	}

	/**
	 * @dataProvider provideTemplateReplacements
	 */
	public function testTemplateReplacements(
		array $replacements,
		$wikiText,
		$expectedWikiText,
		$expectedCount
	) {
		$conversions = new WikiTextConversions( [], [], [], $replacements );
		$cleaner = new WikiTextContentCleaner( $conversions );

		$this->assertSame( $expectedWikiText ?: $wikiText, $cleaner->cleanWikiText( $wikiText ) );
		$this->assertSame( $expectedCount, $cleaner->getLatestNumberOfReplacements() );
	}

}
