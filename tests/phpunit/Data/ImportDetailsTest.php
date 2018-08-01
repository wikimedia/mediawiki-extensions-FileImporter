<?php

namespace FileImporter\Data\Test;

use FileImporter\Data\FileRevision;
use FileImporter\Data\FileRevisions;
use FileImporter\Data\ImportDetails;
use FileImporter\Data\SourceUrl;
use FileImporter\Data\TextRevision;
use FileImporter\Data\TextRevisions;
use OutOfRangeException;
use PHPUnit4And6Compat;
use TitleValue;

/**
 * @covers \FileImporter\Data\ImportDetails
 *
 * @license GPL-2.0-or-later
 * @author Thiemo Kreuz
 */
class ImportDetailsTest extends \PHPUnit\Framework\TestCase {
	use PHPUnit4And6Compat;

	public function testValueObject() {
		$sourceUrl = new SourceUrl( '//SOURCE.URL' );
		$sourceLinkTarget = new TitleValue( NS_FILE, 'PATH/FILENAME.EXT' );
		$textRevisions = new TextRevisions( [] );

		$fileRevisions = $this->createMock( FileRevisions::class );
		$fileRevisions->method( 'toArray' )->willReturn( [] );
		$fileRevisions->method( 'getLatest' )
			->willReturn( $this->newRevision( FileRevision::class, 'IMAGEDISPLAYURL' ) );

		$details = new ImportDetails(
			$sourceUrl,
			$sourceLinkTarget,
			$textRevisions,
			$fileRevisions,
			1
		);

		// Values provided on construction time
		$this->assertSame( $sourceUrl, $details->getSourceUrl(), 'sourceUrl' );
		$this->assertSame( $sourceLinkTarget, $details->getSourceLinkTarget(), 'sourceLinkTarget' );
		$this->assertSame( $textRevisions, $details->getTextRevisions(), 'textRevisions' );
		$this->assertSame( $fileRevisions, $details->getFileRevisions(), 'fileRevisions' );
		$this->assertSame( 1, $details->getNumberOfTemplatesReplaced() );
		$this->assertNull( $details->getCleanedRevisionText() );

		// Derived values
		$this->assertSame( 'FILENAME', $details->getSourceFileName(), 'sourceFileName' );
		$this->assertSame( 'EXT', $details->getSourceFileExtension(), 'sourceFileExtension' );
		$this->assertSame( 'IMAGEDISPLAYURL', $details->getImageDisplayUrl(), 'imageDisplayUrl' );
		$this->assertSame( 40, strlen( $details->getOriginalHash() ), 'originalHash' );
	}

	public function testSetCleanedRevisionText() {
		$details = $this->minimalImportDetails();
		$details->setCleanedRevisionText( 'WIKITEXT' );
		$this->assertSame( 'WIKITEXT', $details->getCleanedRevisionText() );
	}

	public function testMissingExtension() {
		$this->assertSame( '', $this->minimalImportDetails()->getSourceFileExtension() );
	}

	public function testMissingFileRevision() {
		$this->setExpectedException( OutOfRangeException::class );
		$this->minimalImportDetails()->getImageDisplayUrl();
	}

	/**
	 * @dataProvider provideSameHashes
	 */
	public function testSameHashes( ImportDetails $original, ImportDetails $other ) {
		$this->assertSame( $original->getOriginalHash(), $other->getOriginalHash() );
	}

	public function provideSameHashes() {
		$original = $this->minimalImportDetails();

		yield 'same' => [ $original, $original ];
	}

	/**
	 * @dataProvider provideNotSameHashes
	 */
	public function testNotSameHashes( ImportDetails $original, ImportDetails $other ) {
		$this->assertNotSame( $original->getOriginalHash(), $other->getOriginalHash() );
	}

	public function provideNotSameHashes() {
		$sourceUrl = new SourceUrl( '//SOURCE.URL' );
		$sourceLinkTarget = new TitleValue( NS_FILE, 'FILE' );
		$textRevisions = new TextRevisions( [ $this->createMock( TextRevision::class ) ] );
		$fileRevisions = new FileRevisions( [ $this->createMock( FileRevision::class ) ] );

		$original = new ImportDetails(
			$sourceUrl,
			$sourceLinkTarget,
			$textRevisions,
			$fileRevisions
		);

		yield 'other sourceUrl' => [
			$original,
			new ImportDetails(
				new SourceUrl( '//OTHER.URL' ),
				$sourceLinkTarget,
				$textRevisions,
				$fileRevisions
			)
		];

		yield 'other sourceLinkTarget' => [
			$original,
			new ImportDetails(
				$sourceUrl,
				new TitleValue( NS_FILE, 'OTHER' ),
				$textRevisions,
				$fileRevisions
			)
		];

		yield 'other textRevisions length' => [
			$original,
			new ImportDetails(
				$sourceUrl,
				$sourceLinkTarget,
				new TextRevisions( [] ),
				$fileRevisions
			)
		];

		yield 'other fileRevisions length' => [
			$original,
			new ImportDetails(
				$sourceUrl,
				$sourceLinkTarget,
				$textRevisions,
				new FileRevisions( [] )
			)
		];

		yield 'other textRevision sha1' => [
			$original,
			new ImportDetails(
				$sourceUrl,
				$sourceLinkTarget,
				new TextRevisions( [ $this->newRevision( TextRevision::class, 'OTHER' ) ] ),
				$fileRevisions
			)
		];

		yield 'other fileRevision sha1' => [
			$original,
			new ImportDetails(
				$sourceUrl,
				$sourceLinkTarget,
				$textRevisions,
				new FileRevisions( [ $this->newRevision( FileRevision::class, 'OTHER' ) ] )
			)
		];
	}

	private function minimalImportDetails() {
		return new ImportDetails(
			new SourceUrl( '//SOURCE.URL' ),
			new TitleValue( NS_FILE, 'FILE' ),
			new TextRevisions( [] ),
			new FileRevisions( [] )
		);
	}

	/**
	 * @param string $revisionClass Either TextRevision::class or FileRevision::class
	 * @param string $fieldValue All fields will return the same value
	 *
	 * @return TextRevision|FileRevision
	 */
	private function newRevision( $revisionClass, $fieldValue ) {
		$mock = $this->createMock( $revisionClass );
		$mock->method( 'getField' )->willReturn( $fieldValue );
		return $mock;
	}

}
