<?php

namespace FileImporter\Tests\Data;

use FileImporter\Data\WikiTextConversions;

/**
 * @covers \FileImporter\Data\WikiTextConversions
 */
class WikiTextConversionsTest extends \PHPUnit\Framework\TestCase {

	public function provideGoodTemplates() {
		return [
			[ [], '', false ],
			[ [], 'Incomplete', false ],
			[ [ 'Incomplete' ], 'Incomplete', false ],

			[ [ 'Bad' ], 'Template:Bad', true ],
			[ [ 'needs_normalization' ], 'Template:Needs normalization', true ],
			[ [ 'Needs normalization' ], 'template:Needs_normalization', true ],
			[ [ 'CASE INSENSITIVE' ], 'Template:case insensitive', true ],
			[ [ 'български' ], 'Template:БЪЛГАРСКИ', true ],
		];
	}

	/**
	 * @dataProvider provideGoodTemplates
	 */
	public function testIsTemplateGood( array $goodTemplates, $template, $expected ) {
		$conversions = new WikiTextConversions( $goodTemplates, [], [] );
		$this->assertSame( $expected, $conversions->isTemplateGood( $template ) );
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

	public function provideBadTemplates() {
		return [
			[ [], '', false ],
			[ [], 'Incomplete', false ],
			[ [ 'Incomplete' ], 'Incomplete', false ],

			[ [ 'Bad' ], 'Template:Bad', true ],
			[ [ 'needs_normalization' ], 'Template:Needs normalization', true ],
			[ [ 'Needs normalization' ], 'template:Needs_normalization', true ],
			[ [ 'CASE INSENSITIVE' ], 'Template:case insensitive', true ],
			[ [ 'български' ], 'Template:БЪЛГАРСКИ', true ],
		];
	}

	/**
	 * @dataProvider provideBadTemplates
	 */
	public function testIsTemplateBad( array $badTemplates, $template, $expected ) {
		$conversions = new WikiTextConversions( [], $badTemplates, [] );
		$this->assertSame( $expected, $conversions->isTemplateBad( $template ) );
	}

	public function provideBadCategories() {
		return [
			[ [], '', false ],
			[ [], 'Incomplete', false ],
			[ [ 'Incomplete' ], 'Incomplete', false ],

			[ [ 'Bad' ], 'Category:Bad', true ],
			[ [ 'needs_normalization' ], 'Category:Needs normalization', true ],
			[ [ 'Needs normalization' ], 'category:Needs_normalization', true ],
			[ [ 'CASE INSENSITIVE' ], 'Category:case insensitive', true ],
			[ [ 'български' ], 'Category:БЪЛГАРСКИ', true ],
		];
	}

	/**
	 * @dataProvider provideBadCategories
	 */
	public function testIsCategoryBad( array $badCategories, $category, $expected ) {
		$conversions = new WikiTextConversions( [], [], $badCategories );
		$this->assertSame( $expected, $conversions->isCategoryBad( $category ) );
	}

}
