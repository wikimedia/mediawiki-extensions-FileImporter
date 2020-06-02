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

	private static $requiredFieldNames = [
		'description',
		'name',
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
		$fields = array_flip( self::$requiredFieldNames );
		$instance = new FileRevision( $fields );

		$this->expectException( InvalidArgumentException::class );
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
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( $expectedMessage );
		new FileRevision( $fields );
	}

}
