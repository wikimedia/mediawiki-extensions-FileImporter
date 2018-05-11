<?php

namespace FileImporter\Data\Test;

use FileImporter\Data\TextRevision;
use FileImporter\Exceptions\InvalidArgumentException;
use PHPUnit4And6Compat;

/**
 * @covers \FileImporter\Data\TextRevision
 *
 * @license GPL-2.0-or-later
 * @author Thiemo Kreuz
 */
class TextRevisionTest extends \PHPUnit\Framework\TestCase {
	use PHPUnit4And6Compat;

	private static $requiredFieldNames = [
		'*',
		'comment',
		'contentformat',
		'contentmodel',
		'minor',
		'sha1',
		'timestamp',
		'title',
		'user',
	];

	public function testGetters() {
		$fields = array_flip( self::$requiredFieldNames );
		$instance = new TextRevision( $fields );

		$this->assertSame( $fields, $instance->getFields(), 'getFields' );

		foreach ( self::$requiredFieldNames as $expected => $field ) {
			$this->assertSame( $expected, $instance->getField( $field ), "getField($field)" );
		}
	}

	public function testGetNonExistingField() {
		$instance = new TextRevision( array_flip( self::$requiredFieldNames ) );

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
		new TextRevision( $fields );
	}

}
