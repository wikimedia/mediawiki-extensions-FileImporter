<?php

namespace FileImporter\Tests\Data;

use FileImporter\Data\FileRevision;
use FileImporter\Exceptions\InvalidArgumentException;

/**
 * @covers \FileImporter\Data\FileRevision
 *
 * @license GPL-2.0-or-later
 * @author Thiemo Kreuz
 */
class FileRevisionTest extends \MediaWikiUnitTestCase {

	private const REQUIRED_FIELD_NAMES = [
		'description',
		'name',
		'size',
		'thumburl',
		'timestamp',
		'url',
		'user',
	];

	public function testGetters() {
		$fields = array_flip( self::REQUIRED_FIELD_NAMES ) + [ 'sha1' => 'TestSha1' ];
		$instance = new FileRevision( $fields );

		$this->assertSame( $fields, $instance->getFields(), 'getFields' );

		foreach ( self::REQUIRED_FIELD_NAMES as $expected => $field ) {
			$this->assertSame( $expected, $instance->getField( $field ), "getField($field)" );
		}
	}

	public function testSetAndGetNonExistingField() {
		$fields = array_flip( self::REQUIRED_FIELD_NAMES );
		$instance = new FileRevision( $fields );

		$this->assertNull( $instance->getField( 'sha1' ) );
	}

	public function provideMissingField() {
		foreach ( self::REQUIRED_FIELD_NAMES as $field ) {
			$fields = array_flip( self::REQUIRED_FIELD_NAMES );
			unset( $fields[$field] );
			yield [ $fields, ": Missing $field field on construction" ];
		}
	}

	/**
	 * @dataProvider provideMissingField
	 */
	public function testMissingField( array $fields, $expectedMessage ) {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( $expectedMessage );
		new FileRevision( $fields );
	}

}
