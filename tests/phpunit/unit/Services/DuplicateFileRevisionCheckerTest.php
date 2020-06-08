<?php

namespace FileImporter\Tests\Services;

use FileImporter\Data\FileRevision;
use FileImporter\Services\DuplicateFileRevisionChecker;

/**
 * @covers \FileImporter\Services\DuplicateFileRevisionChecker
 *
 * @license GPL-2.0-or-later
 * @author Thiemo Kreuz
 */
class DuplicateFileRevisionCheckerTest extends \MediaWikiUnitTestCase {

	public function testFindDuplicates() {
		$fileRevision = $this->createMock( FileRevision::class );
		$fileRevision->method( 'getField' )->willReturn( 'SHA1' );

		$oldFile = $this->createMock( \File::class );
		$oldFile->method( 'isOld' )->willReturn( true );

		$deletedFile = $this->createMock( \File::class );
		$deletedFile->method( 'isDeleted' )->with( \File::DELETED_FILE )->willReturn( true );

		$wantedFile = $this->createMock( \File::class );

		$fileRepo = $this->createMock( \FileRepo::class );
		$fileRepo->expects( $this->once() )
			->method( 'findBySha1' )
			->with( 'SHA1' )
			->willReturn( [
				$oldFile,
				$deletedFile,
				$wantedFile,
			] );

		$checker = new DuplicateFileRevisionChecker( $fileRepo );
		$result = $checker->findDuplicates( $fileRevision );
		$this->assertSame( [ $wantedFile ], $result );
	}

	public function testFindDuplicates_missingHash() {
		$fileRevision = $this->createMock( FileRevision::class );
		$fileRevision->method( 'getField' )->willReturn( '' );

		$fileRepo = $this->createMock( \FileRepo::class );
		$fileRepo->expects( $this->never() )->method( 'findBySha1' );

		$checker = new DuplicateFileRevisionChecker( $fileRepo );
		$result = $checker->findDuplicates( $fileRevision );
		$this->assertSame( [], $result );
	}

}
