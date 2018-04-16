<?php

namespace FileImporter\Data\Test;

use FileImporter\Data\TextRevision;
use FileImporter\Data\TextRevisions;
use PHPUnit4And6Compat;

/**
 * @covers \FileImporter\Data\TextRevisions
 *
 * @license GPL-2.0-or-later
 * @author Thiemo Kreuz
 */
class TextRevisionsTest extends \PHPUnit\Framework\TestCase {
	use PHPUnit4And6Compat;

	/**
	 * @param string $timestamp
	 *
	 * @return TextRevision
	 */
	private function newTextRevision( $timestamp = '' ) {
		$mock = $this->createMock( TextRevision::class );
		$mock->method( 'getField' )
			->with( 'timestamp' )
			->willReturn( $timestamp );
		return $mock;
	}

	public function testToArray() {
		$revisions = [ $this->newTextRevision() ];
		$instance = new TextRevisions( $revisions );
		$this->assertSame( $revisions, $instance->toArray() );
	}

	public function provideLatestTextRevision() {
		$from2013 = $this->newTextRevision( '2013-11-18T13:19:01Z' );
		$from2014 = $this->newTextRevision( '2014-11-18T13:19:01Z' );
		$from2015 = $this->newTextRevision( '2015-11-18T13:19:01Z' );

		return [
			[ [], null ],
			[ [ $from2013 ], $from2013 ],
			[ [ $from2014 ], $from2014 ],
			[ [ $from2013, $from2014 ], $from2014 ],
			[ [ $from2014, $from2013 ], $from2014 ],
			[ [ $from2014, $from2013, $from2015 ], $from2015 ],
			[ [ $from2015, $from2013, $from2014 ], $from2015 ],
		];
	}

	/**
	 * @dataProvider provideLatestTextRevision
	 */
	public function testGetLatest( array $revisions, $expected ) {
		$instance = new TextRevisions( $revisions );
		$this->assertSame( $expected, $instance->getLatest() );
	}

}
