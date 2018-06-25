<?php

namespace FileImporter\Tests\Data;

use FileImporter\Data\WikiTextConversions;

/**
 * @covers \FileImporter\Data\WikiTextConversions
 */
class WikiTextConversionsTest extends \PHPUnit\Framework\TestCase {

	public function provideCaseInsensitivePageNames() {
		return [
			[ 'Not equal to empty string', '', false ],
			[ '', 'Not equal to empty string', false ],
			[ 'Mismatch', 'Not equal', false ],
			[ 'Identical', 'Identical', true ],
			[ 'needs_normalization', 'Needs normalization', true ],
			[ 'Needs normalization', 'Needs_normalization', true ],
			[ 'Needs normalization', 'Vorlage:Needs_normalization', true ],
			[ 'CASE INSENSITIVE', 'case insensitive', true ],
			[ 'български', 'БЪЛГАРСКИ', true ],
		];
	}

	/**
	 * @dataProvider provideCaseInsensitivePageNames
	 */
	public function testIsTemplateGood( $listed, $requested, $expected ) {
		$conversions = new WikiTextConversions( [ $listed ], [], [] );
		$this->assertSame( $expected, $conversions->isTemplateGood( $requested ) );
	}

	public function provideHasGoodTemplates() {
		return [
			[ [], false ],
			[ [ 'Good' ], true ]
		];
	}

	/**
	 * @dataProvider provideHasGoodTemplates
	 */
	public function testHasGoodTemplates( array $goodTemplates, $expected ) {
		$conversions = new WikiTextConversions( $goodTemplates, [], [] );
		$this->assertSame( $expected, $conversions->hasGoodTemplates() );
	}

	/**
	 * @dataProvider provideCaseInsensitivePageNames
	 */
	public function testIsTemplateBad( $listed, $requested, $expected ) {
		$conversions = new WikiTextConversions( [], [ $listed ], [] );
		$this->assertSame( $expected, $conversions->isTemplateBad( $requested ) );
	}

	/**
	 * @dataProvider provideCaseInsensitivePageNames
	 */
	public function testIsCategoryBad( $listed, $requested, $expected ) {
		$conversions = new WikiTextConversions( [], [], [ $listed ] );
		$this->assertSame( $expected, $conversions->isCategoryBad( $requested ) );
	}

}
