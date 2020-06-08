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

	private const REQUIRED_FIELD_NAMES = [
		'*',
		'comment',
		'contentformat',
		'contentmodel',
		'minor',
		'timestamp',
		'title',
		'user',
		'tags',
	];

	public function testGetters() {
		$fields = array_flip( self::REQUIRED_FIELD_NAMES ) + [ 'sha1' => 'TestSha1' ];
		$instance = new TextRevision( $fields );

		$this->assertSame( $fields, $instance->getFields(), 'getFields' );

		foreach ( $fields as $field => $expected ) {
			$this->assertSame( $expected, $instance->getField( $field ), "getField($field)" );
		}
	}

	public function testSetAndGetNonExistingField() {
		$fields = array_flip( self::REQUIRED_FIELD_NAMES );
		$instance = new TextRevision( $fields );

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
		new TextRevision( $fields );
	}

}
