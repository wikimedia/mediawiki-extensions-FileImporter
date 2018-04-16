<?php

namespace FileImporter\Data\Test;

use FileImporter\Data\FileRevision;
use FileImporter\Data\FileRevisions;
use PHPUnit4And6Compat;

/**
 * @covers \FileImporter\Data\FileRevisions
 *
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class FileRevisionsTest extends \PHPUnit\Framework\TestCase {
	use PHPUnit4And6Compat;

	/**
	 * @param string $timestamp
	 *
	 * @return FileRevision
	 */
	private function getMockFileRevision( $timestamp ) {
		$mock = $this->getMockBuilder( FileRevision::class )
			->disableOriginalConstructor()
			->getMock();
		$mock->method( 'getField' )
			->with( 'timestamp' )
			->willReturn( $timestamp );
		return $mock;
	}

	public function provideGetLatest() {
		$firstFileRevision = $this->getMockFileRevision( '2013-11-18T13:19:01Z' );
		$secondFileRevision = $this->getMockFileRevision( '2014-11-18T13:19:01Z' );
		$thirdFileRevision = $this->getMockFileRevision( '2015-11-18T13:19:01Z' );
		return [
			[ [], null ],
			[ [ $firstFileRevision ], $firstFileRevision ],
			[ [ $secondFileRevision ], $secondFileRevision ],
			[ [ $firstFileRevision, $secondFileRevision ], $secondFileRevision ],
			[ [ $secondFileRevision, $firstFileRevision ], $secondFileRevision ],
			[ [ $secondFileRevision, $firstFileRevision, $thirdFileRevision ], $thirdFileRevision ],
			[ [ $thirdFileRevision, $firstFileRevision, $secondFileRevision ], $thirdFileRevision ],
		];
	}

	/**
	 * @dataProvider provideGetLatest
	 */
	public function testGetLatest( array $fileRevisions, $expected ) {
		$fileRevisionsObject = new FileRevisions( $fileRevisions );
		$this->assertSame( $expected, $fileRevisionsObject->getLatest() );
	}

}
