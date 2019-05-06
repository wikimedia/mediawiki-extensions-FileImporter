<?php

namespace FileImporter\Tests\Services;

use FileImporter\Data\WikitextConversions;
use FileImporter\Exceptions\LocalizedImportException;
use FileImporter\Services\FileDescriptionPageValidator;
use PHPUnit4And6Compat;

/**
 * @covers \FileImporter\Services\FileDescriptionPageValidator
 *
 * @license GPL-2.0-or-later
 */
class FileDescriptionPageValidatorTest extends \PHPUnit\Framework\TestCase {
	use PHPUnit4And6Compat;

	public function testSuccess() {
		$conversions = new WikitextConversions( [], [], [], [], [] );
		$validator = new FileDescriptionPageValidator( $conversions );

		// Provide at least one title to cover the full code-path
		$validator->hasRequiredTemplate( [ [ 'title' => 'Template:Good', 'ns' => NS_TEMPLATE ] ] );
		$validator->validateTemplates( [ [ 'title' => 'Template:Good', 'ns' => NS_TEMPLATE ] ] );
		$validator->validateCategories( [ [ 'title' => 'Category:Good', 'ns' => NS_TEMPLATE ] ] );

		// Nothing else to assert here
		$this->addToAssertionCount( 1 );
	}

	public function testHasAtLeastOneRequiredGoodTemplate() {
		$conversions = new WikitextConversions( [ 'Required1', 'Required2' ], [], [], [], [] );
		$validator = new FileDescriptionPageValidator( $conversions );

		$validator->hasRequiredTemplate( [ [ 'title' => 'Template:Required2', 'ns' => NS_TEMPLATE ] ] );
		$this->addToAssertionCount( 1 );
	}

	public function testMissingRequiredGoodTemplate() {
		$conversions = new WikitextConversions( [ 'Required' ], [], [], [], [] );
		$validator = new FileDescriptionPageValidator( $conversions );

		$this->setExpectedException( LocalizedImportException::class );
		$validator->hasRequiredTemplate( [] );
	}

	public function testBadTemplate() {
		$conversions = new WikitextConversions( [], [ 'Bad' ], [], [], [] );
		$validator = new FileDescriptionPageValidator( $conversions );

		$this->setExpectedException( LocalizedImportException::class );
		$validator->validateTemplates( [ [ 'title' => 'Template:Bad', 'ns' => NS_TEMPLATE ] ] );
	}

	public function testBadCategory() {
		$conversions = new WikitextConversions( [], [], [ 'Bad' ], [], [] );
		$validator = new FileDescriptionPageValidator( $conversions );

		$this->setExpectedException( LocalizedImportException::class );
		$validator->validateCategories( [ [ 'title' => 'Category:Bad', 'ns' => NS_CATEGORY ] ] );
	}

}
