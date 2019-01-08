<?php

namespace FileImporter\Data\Test;

use FileImporter\Data\FileRevision;
use FileImporter\Exceptions\InvalidArgumentException;
use PHPUnit4And6Compat;

/**
 * @covers \FileImporter\Data\FileRevision
 *
 * @license GPL-2.0-or-later
 * @author Thiemo Kreuz
 */
class FileRevisionTest extends \PHPUnit\Framework\TestCase {
	use PHPUnit4And6Compat;

	private static $requiredFieldNames = [
		'description',
		'name',
		'sha1',
		'size',
		'thumburl',
		'timestamp',
		'url',
		'user',
	];

	public function testGetters() {
		$fields = array_flip( self::$requiredFieldNames );
		$instance = new FileRevision( $fields );

		$this->assertSame( $fields, $instance->getFields(), 'getFields' );

		foreach ( self::$requiredFieldNames as $expected => $field ) {
			$this->assertSame( $expected, $instance->getField( $field ), "getField($field)" );
		}
	}

	public function testSetAndGetNonExistingField() {
		// The class should accept additional fields, but getField should warn when accessing them
		$fields = array_flip( self::$requiredFieldNames ) + [ 'invalid' => null ];
		$instance = new FileRevision( $fields );

		$this->setExpectedException( InvalidArgumentException::class );
		$instance->getField( 'invalid' );
	}

	public function provideMissingField() {
		foreach ( self::$requiredFieldNames as $field ) {
			$fields = array_flip( self::$requiredFieldNames );
			unset( $fields[$field] );
			yield [ $fields, ": Missing $field field on construction" ];
		}
	}

	/**
	 * @dataProvider provideMissingField
	 */
	public function testMissingField( array $fields, $expectedMessage ) {
		$this->setExpectedException( InvalidArgumentException::class, $expectedMessage );
		new FileRevision( $fields );
	}

}
