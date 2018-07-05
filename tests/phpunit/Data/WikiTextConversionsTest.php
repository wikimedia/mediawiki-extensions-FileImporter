<?php

namespace FileImporter\Tests\Data;

use FileImporter\Data\WikiTextConversions;
use InvalidArgumentException;

/**
 * @covers \FileImporter\Data\WikiTextConversions
 *
 * @license GPL-2.0-or-later
 */
class WikiTextConversionsTest extends \PHPUnit\Framework\TestCase {
	use \PHPUnit4And6Compat;

	public function testInvalidTargetTemplateName() {
		$this->setExpectedException( InvalidArgumentException::class, 'targetTemplate' );
		new WikiTextConversions( [], [], [], [ [ 'targetTemplate' => '' ] ] );
	}

	public function testMissingTemplateParameters() {
		$this->setExpectedException( InvalidArgumentException::class, 'parameters' );
		new WikiTextConversions( [], [], [], [ [ 'targetTemplate' => 'a' ] ] );
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
		$conversions = new WikiTextConversions( [ $listed ], [], [], [] );
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
		$conversions = new WikiTextConversions( $goodTemplates, [], [], [] );
		$this->assertSame( $expected, $conversions->hasGoodTemplates() );
	}

	/**
	 * @dataProvider provideCaseInsensitivePageNames
	 */
	public function testIsTemplateBad( $listed, $requested, $expected ) {
		$conversions = new WikiTextConversions( [], [ $listed ], [], [] );
		$this->assertSame( $expected, $conversions->isTemplateBad( $requested ) );
	}

	/**
	 * @dataProvider provideCaseInsensitivePageNames
	 */
	public function testIsCategoryBad( $listed, $requested, $expected ) {
		$conversions = new WikiTextConversions( [], [], [ $listed ], [] );
		$this->assertSame( $expected, $conversions->isCategoryBad( $requested ) );
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
		$conversions = new WikiTextConversions( [], [], [], $replacements );
		$this->assertSame( $expected, $conversions->swapTemplate( $requested ) );
	}

	public function provideTemplateParameterReplacements() {
		return [
			'empty' => [
				[],
				[]
			],
			'empty string' => [
				[ 's1' => [ 'sourceParameters' => '' ] ],
				[]
			],
			'empty array' => [
				[ 't1' => [ 'sourceParameters' => [] ] ],
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
	public function testGetTemplateParameters( array $replacements, $expected ) {
		$conversions = new WikiTextConversions( [], [], [], [ 's' => [
			'targetTemplate' => 't',
			'parameters' => $replacements,
		] ] );
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
	public function testGetRequiredTemplateParameters( array $replacements, $expected ) {
		$conversions = new WikiTextConversions( [], [], [], [ 's' => [
			'targetTemplate' => 't',
			'parameters' => $replacements,
		] ] );
		$this->assertSame( $expected, $conversions->getRequiredTemplateParameters( 's' ) );
	}

}
