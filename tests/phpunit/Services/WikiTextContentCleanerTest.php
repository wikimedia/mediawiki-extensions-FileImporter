<?php

namespace FileImporter\Tests\Services;

use FileImporter\Data\TextRevision;
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
		$revision = $this->newTextRevision( $wikiText );
		$conversions = new WikiTextConversions( [], [], [], $replacements );
		$cleaner = new WikiTextContentCleaner( $conversions );

		$this->assertSame( $expectedCount, $cleaner->cleanWikiText( $revision ) );
		$this->assertSame( $expectedWikiText ?: $wikiText, $revision->getField( '*' ) );
	}

	/**
	 * @param string $wikitext
	 *
	 * @return TextRevision
	 */
	private function newTextRevision( $wikitext ) {
		return new TextRevision( [
			'minor' => null,
			'user' => null,
			'timestamp' => null,
			'sha1' => null,
			'contentmodel' => null,
			'contentformat' => null,
			'comment' => null,
			'*' => $wikitext,
			'title' => null,
		] );
	}

}
