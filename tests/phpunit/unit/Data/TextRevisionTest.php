<?php

namespace FileImporter\Tests\Data;

use FileImporter\Data\TextRevision;
use FileImporter\Exceptions\InvalidArgumentException;

/**
 * @covers \FileImporter\Data\TextRevision
 *
 * @license GPL-2.0-or-later
 * @author Thiemo Kreuz
 */
class TextRevisionTest extends \MediaWikiUnitTestCase {

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
		'tags',
	];

	public function testGetters() {
		$fields = array_flip( self::$requiredFieldNames );
		$instance = new TextRevision( $fields );

		$this->assertSame( $fields, $instance->getFields(), 'getFields' );

		foreach ( $fields as $field => $expected ) {
			$this->assertSame( $expected, $instance->getField( $field ), "getField($field)" );
		}
	}

	public function testSetField() {
		$instance = new TextRevision( array_flip( self::$requiredFieldNames ) );
		$instance->setField( 'comment', 'changed' );
		$this->assertSame( 'changed', $instance->getField( 'comment' ) );
	}

	public function testSetAndGetNonExistingField() {
		// The class should accept additional fields, but getField should warn when accessing them
		$fields = array_flip( self::$requiredFieldNames ) + [ 'invalid' => null ];
		$instance = new TextRevision( $fields );

		$this->expectException( InvalidArgumentException::class );
		$instance->getField( 'invalid' );
	}

	public function testSetNonExistingField() {
		$instance = new TextRevision( array_flip( self::$requiredFieldNames ) );

		$this->expectException( InvalidArgumentException::class );
		$instance->setField( 'invalid', null );
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
		new TextRevision( $fields );
	}

}
