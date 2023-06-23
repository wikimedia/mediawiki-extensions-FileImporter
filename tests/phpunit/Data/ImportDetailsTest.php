<?php

namespace FileImporter\Tests\Data;

use FileImporter\Data\FileRevision;
use FileImporter\Data\FileRevisions;
use FileImporter\Data\ImportDetails;
use FileImporter\Data\SourceUrl;
use FileImporter\Data\TextRevision;
use FileImporter\Data\TextRevisions;
use MediaWiki\Revision\SlotRecord;
use TitleValue;

/**
 * @covers \FileImporter\Data\ImportDetails
 *
 * @license GPL-2.0-or-later
 * @author Thiemo Kreuz
 */
class ImportDetailsTest extends \PHPUnit\Framework\TestCase {

	public function testValueObject() {
		$sourceUrl = new SourceUrl( '//SOURCE.URL' );
		$sourceLinkTarget = new TitleValue( NS_FILE, 'PATH/FILENAME.EXT' );
		$textRevisions = new TextRevisions( [ self::createTextRevision() ] );

		$fileRevisions = $this->createMock( FileRevisions::class );
		$fileRevisions->method( 'toArray' )->willReturn( [] );
		$fileRevisions->method( 'getLatest' )
			->willReturn( self::createFileRevision( [ 'thumburl' => 'IMAGEDISPLAYURL' ] ) );

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

	public function testSetters() {
		$details = self::minimalImportDetails();

		$this->assertNull( $details->getPageLanguage() );
		$this->assertSame( [], $details->getTemplates() );
		$this->assertSame( [], $details->getCategories() );

		$details->setPageLanguage( 'de' );
		$details->setTemplates( [ 'T' ] );
		$details->setCategories( [ 'C' ] );

		$this->assertSame( 'de', $details->getPageLanguage() );
		$this->assertSame( [ 'T' ], $details->getTemplates() );
		$this->assertSame( [ 'C' ], $details->getCategories() );
	}

	public function testMissingExtension() {
		$this->assertSame( '', self::minimalImportDetails()->getSourceFileExtension() );
	}

	public function testInvalidFileRevisionTimestamp() {
		$this->expectException( \LogicException::class );
		self::minimalImportDetails()->getImageDisplayUrl();
	}

	/**
	 * @dataProvider provideSameHashes
	 */
	public function testSameHashes( ImportDetails $original, ImportDetails $other ) {
		$this->assertSame( $original->getOriginalHash(), $other->getOriginalHash() );
	}

	public static function provideSameHashes() {
		$original = self::minimalImportDetails();

		yield 'same' => [ $original, $original ];
	}

	/**
	 * @dataProvider provideNotSameHashes
	 */
	public function testNotSameHashes( ImportDetails $original, ImportDetails $other ) {
		$this->assertNotSame( $original->getOriginalHash(), $other->getOriginalHash() );
	}

	public static function provideNotSameHashes() {
		$sourceUrl = new SourceUrl( '//SOURCE.URL' );
		$sourceLinkTarget = new TitleValue( NS_FILE, 'FILE' );
		$textRevisions = new TextRevisions( [ self::createTextRevision() ] );
		$fileRevisions = new FileRevisions( [ self::createFileRevision() ] );

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
				new TextRevisions( [
					self::createTextRevision(),
					self::createTextRevision(),
				] ),
				$fileRevisions
			)
		];

		yield 'other fileRevisions length' => [
			$original,
			new ImportDetails(
				$sourceUrl,
				$sourceLinkTarget,
				$textRevisions,
				new FileRevisions( [
					self::createFileRevision(),
					self::createFileRevision(),
				] )
			)
		];

		yield 'other textRevision sha1' => [
			$original,
			new ImportDetails(
				$sourceUrl,
				$sourceLinkTarget,
				new TextRevisions( [ self::createTextRevision( [ 'sha1' => 'OTHER' ] ) ] ),
				$fileRevisions
			)
		];

		yield 'other fileRevision sha1' => [
			$original,
			new ImportDetails(
				$sourceUrl,
				$sourceLinkTarget,
				$textRevisions,
				new FileRevisions( [ self::createFileRevision( [ 'sha1' => 'OTHER' ] ) ] )
			)
		];
	}

	private static function minimalImportDetails(): ImportDetails {
		return new ImportDetails(
			new SourceUrl( '//SOURCE.URL' ),
			new TitleValue( NS_FILE, 'FILE' ),
			new TextRevisions( [ self::createTextRevision() ] ),
			new FileRevisions( [ self::createFileRevision() ] )
		);
	}

	/**
	 * @param array $fields
	 * @return FileRevision
	 */
	private static function createFileRevision( array $fields = [] ): FileRevision {
		return new FileRevision(
			$fields + [
				'name' => '',
				'description' => '',
				'user' => '',
				'timestamp' => '',
				'size' => 0,
				'thumburl' => '',
				'url' => '',
			]
		);
	}

	/**
	 * @param array $fields
	 * @return TextRevision
	 */
	private static function createTextRevision( array $fields = [] ): TextRevision {
		return new TextRevision(
			$fields + [
				'minor' => false,
				'user' => '',
				'timestamp' => '',
				'comment' => '',
				'slots' => [
					SlotRecord::MAIN => [
						'contentmodel' => '',
						'contentformat' => '',
						'content' => ''
					]
				],
				'title' => '',
				'tags' => '',
			]
		);
	}

}
