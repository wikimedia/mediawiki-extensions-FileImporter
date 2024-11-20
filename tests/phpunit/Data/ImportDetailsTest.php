<?php
declare( strict_types = 1 );

namespace FileImporter\Tests\Data;

use FileImporter\Data\FileRevision;
use FileImporter\Data\FileRevisions;
use FileImporter\Data\ImportDetails;
use FileImporter\Data\SourceUrl;
use FileImporter\Data\TextRevision;
use FileImporter\Data\TextRevisions;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Title\TitleValue;

/**
 * @covers \FileImporter\Data\ImportDetails
 *
 * @license GPL-2.0-or-later
 * @author Thiemo Kreuz
 */
class ImportDetailsTest extends \MediaWikiIntegrationTestCase {

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
	public function testNotSameHashes(
		?string $sourceUrl,
		?string $linkTarget,
		?TextRevisions $textRevisions,
		?FileRevisions $fileRevisions
	) {
		$original = new ImportDetails(
			new SourceUrl( '//SOURCE.URL' ),
			new TitleValue( NS_FILE, 'FILE' ),
			new TextRevisions( [ self::createTextRevision() ] ),
			new FileRevisions( [ self::createFileRevision() ] )
		);
		$other = new ImportDetails(
			$sourceUrl ? new SourceUrl( $sourceUrl ) : $original->getSourceUrl(),
			$linkTarget ? new TitleValue( NS_FILE, $linkTarget ) : $original->getSourceLinkTarget(),
			$textRevisions ?? $original->getTextRevisions(),
			$fileRevisions ?? $original->getFileRevisions()
		);
		$this->assertNotSame( $original->getOriginalHash(), $other->getOriginalHash() );
	}

	public static function provideNotSameHashes() {
		yield 'other sourceUrl' => [
			'//OTHER.URL',
			null,
			null,
			null,
		];

		yield 'other sourceLinkTarget' => [
			null,
			'OTHER',
			null,
			null,
		];

		yield 'other textRevisions length' => [
			null,
			null,
			new TextRevisions( [ self::createTextRevision(), self::createTextRevision() ] ),
			null,
		];

		yield 'other fileRevisions length' => [
			null,
			null,
			null,
			new FileRevisions( [ self::createFileRevision(), self::createFileRevision() ] )
		];

		yield 'other textRevision sha1' => [
			null,
			null,
			new TextRevisions( [ self::createTextRevision( [ 'sha1' => 'OTHER' ] ) ] ),
			null,
		];

		yield 'other fileRevision sha1' => [
			null,
			null,
			null,
			new FileRevisions( [ self::createFileRevision( [ 'sha1' => 'OTHER' ] ) ] )
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
