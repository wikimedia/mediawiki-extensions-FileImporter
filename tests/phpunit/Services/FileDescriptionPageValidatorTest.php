<?php

namespace FileImporter\Tests\Services;

use FileImporter\Data\WikitextConversions;
use FileImporter\Exceptions\LocalizedImportException;
use FileImporter\Services\FileDescriptionPageValidator;

/**
 * @covers \FileImporter\Services\FileDescriptionPageValidator
 *
 * @license GPL-2.0-or-later
 */
class FileDescriptionPageValidatorTest extends \MediaWikiIntegrationTestCase {

	public function testSuccess() {
		$conversions = new WikitextConversions( [] );
		$validator = new FileDescriptionPageValidator( $conversions );

		// Provide at least one title to cover the full code-path
		$validator->hasRequiredTemplate( [ 'Template:Good' ] );
		$validator->validateTemplates( [ 'Template:Good' ] );
		$validator->validateCategories( [ 'Category:Good' ] );

		// Nothing else to assert here
		$this->addToAssertionCount( 1 );
	}

	public function testHasAtLeastOneRequiredGoodTemplate() {
		$conversions = new WikitextConversions( [
			WikitextConversions::REQUIRED_TEMPLATES => [ 'Required1', 'Required2' ],
		] );
		$validator = new FileDescriptionPageValidator( $conversions );

		$validator->hasRequiredTemplate( [ 'Template:Required2' ] );
		$this->addToAssertionCount( 1 );
	}

	public function testMissingRequiredGoodTemplate() {
		$conversions = new WikitextConversions( [
			WikitextConversions::REQUIRED_TEMPLATES => [ 'Required' ],
		] );
		$validator = new FileDescriptionPageValidator( $conversions );

		$this->expectException( LocalizedImportException::class );
		$validator->hasRequiredTemplate( [] );
	}

	public function testBadTemplate() {
		$conversions = new WikitextConversions( [
			WikitextConversions::FORBIDDEN_TEMPLATES => [ 'Bad' ],
		] );
		$validator = new FileDescriptionPageValidator( $conversions );

		$this->expectException( LocalizedImportException::class );
		$validator->validateTemplates( [ 'Template:Bad' ] );
	}

	public function testBadCategory() {
		$conversions = new WikitextConversions( [
			WikitextConversions::FORBIDDEN_CATEGORIES => [ 'Bad' ],
		] );
		$validator = new FileDescriptionPageValidator( $conversions );

		$this->expectException( LocalizedImportException::class );
		$validator->validateCategories( [ 'Category:Bad' ] );
	}

}
