<?php

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
		new WikitextConversions( [], [], [], [], [ [ 'targetTemplate' => '' ] ] );
	}

	public function testMissingTemplateParameters() {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'parameters' );
		new WikitextConversions( [], [], [], [], [ [ 'targetTemplate' => 'a' ] ] );
	}

	public function testHeadingReplacements() {
		$conversions = new WikitextConversions( [ WikitextConversions::HEADING_REPLACEMENTS => [
			'a' => 'b',
		] ] );
		$this->assertSame( 'b', $conversions->swapHeading( 'a' ) );
	}

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
		$conversions = new WikitextConversions( [ $listed ], [], [], [], [] );
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
		$conversions = new WikitextConversions( [
			WikitextConversions::REQUIRED_TEMPLATES => $goodTemplates,
		] );
		$this->assertSame( $expected, $conversions->hasGoodTemplates() );
	}

	/**
	 * @dataProvider provideCaseInsensitivePageNames
	 */
	public function testIsTemplateBad( $listed, $requested, $expected ) {
		$conversions = new WikitextConversions( [], [ $listed ], [], [], [] );
		$this->assertSame( $expected, $conversions->isTemplateBad( $requested ) );
	}

	/**
	 * @dataProvider provideCaseInsensitivePageNames
	 */
	public function testIsCategoryBad( $listed, $requested, $expected ) {
		$conversions = new WikitextConversions( [], [], [ $listed ], [], [] );
		$this->assertSame( $expected, $conversions->isCategoryBad( $requested ) );
	}

	public function testIsObsoleteTemplate() {
		$conversions = new WikitextConversions( [], [], [], [ 'Л_Г И' ], [] );
		$this->assertTrue( $conversions->isObsoleteTemplate( 'л г_и' ) );
	}

	public function provideTemplateReplacements() {
		return [
			'empty' => [ [], 'a', false ],
			'no substring matching' => [ [ 'a' => 'b' ], 'aa', false ],
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
	public function testTemplateReplacements( array $replacements, $requested, $expected ) {
		$conversions = new WikitextConversions( [], [], [], [], $replacements );
		$this->assertSame( $expected, $conversions->swapTemplate( $requested ) );
	}

	public function provideTemplateParameterReplacements() {
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
		$conversions = new WikitextConversions( [], [], [], [], [ 's' => [
			'targetTemplate' => 't',
			'parameters' => $replacements,
		] ] );
		$expected = array_map( static function ( $target ) {
			return [ 'target' => $target, 'addLanguageTemplate' => false ];
		}, $expected );
		$this->assertSame( $expected, $conversions->getTemplateParameters( 's' ) );
	}

	public function provideRequiredTemplateParameters() {
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
		$conversions = new WikitextConversions( [], [], [], [], [ 's' => [
			'targetTemplate' => 't',
			'parameters' => $replacements,
		] ] );
		$this->assertSame( $expected, $conversions->getRequiredTemplateParameters( 's' ) );
	}

}
