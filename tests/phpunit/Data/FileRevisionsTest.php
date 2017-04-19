<?php

namespace FileImporter\Data\Test;

use FileImporter\Data\FileRevision;
use FileImporter\Data\FileRevisions;
use PHPUnit_Framework_TestCase;

class FileRevisionsTest extends PHPUnit_Framework_TestCase {

	private function getMockFileRevision( $timestamp ) {
		$mock = $this->getMockBuilder( FileRevision::class )
			->disableOriginalConstructor()
			->getMock();
		$mock->expects( $this->any() )
			->method( 'getField' )
			->with( 'timestamp' )
			->will( $this->returnValue( $timestamp ) );
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
