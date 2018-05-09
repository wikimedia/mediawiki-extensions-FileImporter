<?php

namespace FileImporter\Data\Test;

use FileImporter\Data\FileRevision;
use FileImporter\Data\FileRevisions;
use FileImporter\Data\ImportDetails;
use FileImporter\Data\SourceUrl;
use FileImporter\Data\TextRevision;
use FileImporter\Data\TextRevisions;
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

		$fileRevision = $this->createMock( FileRevision::class );
		$fileRevision->method( 'getField' )->willReturn( 'IMAGEDISPLAYURL' );

		$fileRevisions = $this->createMock( FileRevisions::class );
		$fileRevisions->method( 'toArray' )->willReturn( [] );
		$fileRevisions->method( 'getLatest' )->willReturn( $fileRevision );

		$details = new ImportDetails(
			$sourceUrl,
			$sourceLinkTarget,
			$textRevisions,
			$fileRevisions
		);

		// Values provided on construction time
		$this->assertSame( $sourceUrl, $details->getSourceUrl(), 'sourceUrl' );
		$this->assertSame( $sourceLinkTarget, $details->getSourceLinkTarget(), 'sourceLinkTarget' );
		$this->assertSame( $textRevisions, $details->getTextRevisions(), 'textRevisions' );
		$this->assertSame( $fileRevisions, $details->getFileRevisions(), 'fileRevisions' );

		// Derived values
		$this->assertSame( 'FILENAME', $details->getSourceFileName(), 'sourceFileName' );
		$this->assertSame( 'EXT', $details->getSourceFileExtension(), 'sourceFileExtension' );
		$this->assertSame( 'IMAGEDISPLAYURL', $details->getImageDisplayUrl(), 'imageDisplayUrl' );
		$this->assertSame( 40, strlen( $details->getOriginalHash() ), 'originalHash' );
	}

	/**
	 * @dataProvider provideSameHashes
	 */
	public function testSameHashes( ImportDetails $original, ImportDetails $other ) {
		$this->assertSame( $original->getOriginalHash(), $other->getOriginalHash() );
	}

	public function provideSameHashes() {
		$sourceUrl = new SourceUrl( '//SOURCE.URL' );
		$sourceLinkTarget = new TitleValue( NS_FILE, 'FILE' );
		$textRevisions = new TextRevisions( [] );
		$fileRevisions = new FileRevisions( [] );

		$original = new ImportDetails(
			$sourceUrl,
			$sourceLinkTarget,
			$textRevisions,
			$fileRevisions
		);

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

		$textRevision = $this->createMock( TextRevision::class );
		$textRevision->method( 'getField' )->willReturn( 'OTHER' );
		yield 'other textRevision sha1' => [
			$original,
			new ImportDetails(
				$sourceUrl,
				$sourceLinkTarget,
				new TextRevisions( [ $textRevision ] ),
				$fileRevisions
			)
		];

		$fileRevision = $this->createMock( FileRevision::class );
		$fileRevision->method( 'getField' )->willReturn( 'OTHER' );
		yield 'other fileRevision sha1' => [
			$original,
			new ImportDetails(
				$sourceUrl,
				$sourceLinkTarget,
				$textRevisions,
				new FileRevisions( [ $fileRevision ] )
			)
		];
	}

}
