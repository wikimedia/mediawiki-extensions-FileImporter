<?php

namespace FileImporter\Tests\Data;

use FileImporter\Data\TextRevision;
use FileImporter\Data\TextRevisions;

/**
 * @covers \FileImporter\Data\TextRevisions
 *
 * @license GPL-2.0-or-later
 * @author Thiemo Kreuz
 */
class TextRevisionsTest extends \MediaWikiUnitTestCase {

	/**
	 * @param string $timestamp
	 *
	 * @return TextRevision
	 */
	private function newTextRevision( $timestamp = '' ): TextRevision {
		$mock = $this->createMock( TextRevision::class );
		$mock->method( 'getField' )
			->with( 'timestamp' )
			->willReturn( $timestamp );
		return $mock;
	}

	public function testCanNotBeEmpty() {
		$this->expectException( \InvalidArgumentException::class );
		new TextRevisions( [] );
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
