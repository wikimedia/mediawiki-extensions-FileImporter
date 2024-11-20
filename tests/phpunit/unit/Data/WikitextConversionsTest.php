<?php
declare( strict_types = 1 );

namespace FileImporter\Tests\Data;

use FileImporter\Data\WikitextConversions;
use InvalidArgumentException;

/**
 * @covers \FileImporter\Data\WikitextConversions
 *
 * @license GPL-2.0-or-later
 */
class WikitextConversionsTest extends \MediaWikiUnitTestCase {

	public function testInvalidTargetTemplateName() {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'targetTemplate' );
		new WikitextConversions( [
			WikitextConversions::TEMPLATE_TRANSFORMATIONS => [ [ 'targetTemplate' => '' ] ],
		] );
	}

	public function testMissingTemplateParameters() {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'parameters' );
		new WikitextConversions( [
			WikitextConversions::TEMPLATE_TRANSFORMATIONS => [ [ 'targetTemplate' => 'a' ] ],
		] );
	}

	public function testHeadingReplacements() {
		$conversions = new WikitextConversions( [ WikitextConversions::HEADING_REPLACEMENTS => [
			'a' => 'b',
		] ] );
		$this->assertSame( 'b', $conversions->swapHeading( 'a' ) );
	}

	public static function provideCaseInsensitivePageNames() {
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
	public function testIsTemplateGood( string $listed, string $requested, bool $expected ) {
		$conversions = new WikitextConversions( [
			WikitextConversions::REQUIRED_TEMPLATES => [ $listed ],
		] );
		$this->assertSame( $expected, $conversions->isTemplateGood( $requested ) );
	}

	public static function provideHasGoodTemplates() {
		return [
			[ [], false ],
			[ [ 'Good' ], true ]
		];
	}

	/**
	 * @dataProvider provideHasGoodTemplates
	 */
	public function testHasGoodTemplates( array $goodTemplates, bool $expected ) {
		$conversions = new WikitextConversions( [
			WikitextConversions::REQUIRED_TEMPLATES => $goodTemplates,
		] );
		$this->assertSame( $expected, $conversions->hasGoodTemplates() );
	}

	/**
	 * @dataProvider provideCaseInsensitivePageNames
	 */
	public function testIsTemplateBad( string $listed, string $requested, bool $expected ) {
		$conversions = new WikitextConversions( [
			WikitextConversions::FORBIDDEN_TEMPLATES => [ $listed ],
		] );
		$this->assertSame( $expected, $conversions->isTemplateBad( $requested ) );
	}

	/**
	 * @dataProvider provideCaseInsensitivePageNames
	 */
	public function testIsCategoryBad( string $listed, string $requested, bool $expected ) {
		$conversions = new WikitextConversions( [
			WikitextConversions::FORBIDDEN_CATEGORIES => [ $listed ],
		] );
		$this->assertSame( $expected, $conversions->isCategoryBad( $requested ) );
	}

	public function testIsObsoleteTemplate() {
		$conversions = new WikitextConversions( [
			WikitextConversions::OBSOLETE_TEMPLATES => [ 'Л_Г И' ],
		] );
		$this->assertTrue( $conversions->isObsoleteTemplate( 'л г_и' ) );
	}

	public static function provideTemplateReplacements() {
		return [
			'empty' => [ [], 'a', null ],
			'no substring matching' => [ [ 'a' => 'b' ], 'aa', null ],
			'rule is trimmed' => [ [ ' a ' => ' b ' ], 'a', 'b' ],
			'input is trimmed' => [ [ 'a' => 'b' ], ' a ', 'b' ],
			'rule is normalized' => [ [ 'a_a' => 'b_b' ], 'a a', 'b b' ],
			'input is normalized' => [ [ 'a a' => 'b b' ], 'a_a', 'b b' ],
			'rule is case-insensitive' => [ [ 'A A' => 'B B' ], 'a a', 'B B' ],
			'input is case-insensitive' => [ [ 'a a' => 'b b' ], 'A A', 'b b' ],
		];
	}

	/**
	 * @dataProvider provideTemplateReplacements
	 */
	public function testTemplateReplacements( array $replacements, string $requested, ?string $expected ) {
		$conversions = new WikitextConversions( [
			WikitextConversions::TEMPLATE_TRANSFORMATIONS => $replacements,
		] );
		$this->assertSame( $expected, $conversions->swapTemplate( $requested ) );
	}

	public static function provideTemplateParameterReplacements() {
		return [
			'empty' => [
				[],
				[]
			],
			'incomplete configuration' => [
				[ 't1' => [] ],
				[]
			],
			'empty string' => [
				[ 't1' => [ 'sourceParameters' => '' ] ],
				[]
			],
			'but the string "0" is not empty' => [
				[ 't1' => [ 'sourceParameters' => '0' ] ],
				[ 0 => 't1' ]
			],
			'empty array' => [
				[ 't1' => [ 'sourceParameters' => [] ] ],
				[]
			],
			'empty array element' => [
				[ 't1' => [ 'sourceParameters' => [ '' ] ] ],
				[]
			],
			'string' => [
				[ 't1' => [ 'sourceParameters' => 's1' ] ],
				[ 's1' => 't1' ]
			],
			'array' => [
				[ 't1' => [ 'sourceParameters' => [ 's1', 'alias' ] ] ],
				[ 's1' => 't1', 'alias' => 't1' ]
			],
		];
	}

	/**
	 * @dataProvider provideTemplateParameterReplacements
	 */
	public function testGetTemplateParameters( array $replacements, array $expected ) {
		$conversions = new WikitextConversions( [
			WikitextConversions::TEMPLATE_TRANSFORMATIONS => [ 's' => [
				'targetTemplate' => 't',
				'parameters' => $replacements,
			] ],
		] );
		$expected = array_map( static function ( string $target ): array {
			return [ 'target' => $target, 'addLanguageTemplate' => false ];
		}, $expected );
		$this->assertSame( $expected, $conversions->getTemplateParameters( 's' ) );
	}

	public static function provideRequiredTemplateParameters() {
		return [
			'empty' => [
				[],
				[]
			],
			'flag not set' => [
				[ 't1' => [] ],
				[]
			],
			'not required' => [
				[ 't1' => [ 'addIfMissing' => false ] ],
				[]
			],
			'no value' => [
				[ 't1' => [ 'addIfMissing' => true ] ],
				[ 't1' => '' ]
			],
			'non-empty value' => [
				[ 't1' => [ 'addIfMissing' => true, 'value' => '…' ] ],
				[ 't1' => '…' ]
			],
		];
	}

	/**
	 * @dataProvider provideRequiredTemplateParameters
	 */
	public function testGetRequiredTemplateParameters( array $replacements, array $expected ) {
		$conversions = new WikitextConversions( [
			WikitextConversions::TEMPLATE_TRANSFORMATIONS => [ 's' => [
				'targetTemplate' => 't',
				'parameters' => $replacements,
			] ],
		] );
		$this->assertSame( $expected, $conversions->getRequiredTemplateParameters( 's' ) );
	}

}
