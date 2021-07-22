<?php

namespace FileImporter\Tests\Data;

use FileImporter\Data\FileRevision;
use FileImporter\Data\FileRevisions;

/**
 * @covers \FileImporter\Data\FileRevisions
 *
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class FileRevisionsTest extends \MediaWikiUnitTestCase {

	/**
	 * @param string $timestamp
	 * @param bool $isOld
	 *
	 * @return FileRevision
	 */
	private function newFileRevision( string $timestamp, bool $isOld = false ): FileRevision {
		$mock = $this->createMock( FileRevision::class );
		$mock->method( 'getField' )
			->willReturnMap( [
				[ 'timestamp', $timestamp ],
				[ 'archivename', $isOld ? '1' : null ],
			] );
		return $mock;
	}

	public function testCanNotBeEmpty() {
		$this->expectException( \InvalidArgumentException::class );
		new FileRevisions( [] );
	}

	public function testToArray() {
		$revisions = [ $this->newFileRevision( '' ) ];
		$instance = new FileRevisions( $revisions );
		$this->assertSame( $revisions, $instance->toArray() );
	}

	public function provideGetLatest() {
		$firstFileRevision = $this->newFileRevision( '2013-11-18T13:19:01Z' );
		$secondFileRevision = $this->newFileRevision( '2014-11-18T13:19:01Z' );
		$thirdFileRevision = $this->newFileRevision( '2015-11-18T13:19:01Z' );
		$reverted = $this->newFileRevision( '2016-11-18T13:19:01Z', true );

		return [
			[ [ $firstFileRevision ], $firstFileRevision ],
			[ [ $secondFileRevision ], $secondFileRevision ],
			[ [ $firstFileRevision, $secondFileRevision ], $secondFileRevision ],
			[ [ $secondFileRevision, $firstFileRevision ], $secondFileRevision ],
			[ [ $secondFileRevision, $firstFileRevision, $thirdFileRevision ], $thirdFileRevision ],
			[ [ $thirdFileRevision, $firstFileRevision, $secondFileRevision ], $thirdFileRevision ],

			// Ignore archived revisions, no matter which order
			[ [ $thirdFileRevision, $reverted ], $thirdFileRevision ],
			[ [ $reverted, $thirdFileRevision ], $thirdFileRevision ],
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
