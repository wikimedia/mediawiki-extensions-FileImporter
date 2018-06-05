<?php

namespace FileImporter\Tests\Services;

use FileImporter\Data\WikiTextConversions;
use FileImporter\Exceptions\LocalizedImportException;
use FileImporter\Services\WikiTextContentValidator;
use PHPUnit4And6Compat;

/**
 * @covers \FileImporter\Services\WikiTextContentValidator
 */
class WikiTextContentValidatorTest extends \PHPUnit\Framework\TestCase {
	use PHPUnit4And6Compat;

	public function testSuccess() {
		$conversions = new WikiTextConversions( [], [] );
		$validator = new WikiTextContentValidator( $conversions );

		// Provide at least one title to cover the full code-path
		$validator->validateTemplates( [ [ 'title' => 'Template:Good' ] ] );
		$validator->validateCategories( [ [ 'title' => 'Category:Good' ] ] );

		// Nothing else to assert here
		$this->addToAssertionCount( 1 );
	}

	public function testBadTemplate() {
		$conversions = new WikiTextConversions( [ 'Bad' ], [] );
		$validator = new WikiTextContentValidator( $conversions );

		$this->setExpectedException( LocalizedImportException::class );
		$validator->validateTemplates( [ [ 'title' => 'Template:Bad' ] ] );
	}

	public function testBadCategory() {
		$conversions = new WikiTextConversions( [], [ 'Bad' ] );
		$validator = new WikiTextContentValidator( $conversions );

		$this->setExpectedException( LocalizedImportException::class );
		$validator->validateCategories( [ [ 'title' => 'Category:Bad' ] ] );
	}

}
