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

	public function testSha1Fallback() {
		$fields = array_flip( self::REQUIRED_FIELD_NAMES );
		$instance = new TextRevision( $fields );
		$this->assertSame( '', $instance->getField( 'sha1' ) );
	}

	public function testSetField() {
		$instance = new TextRevision( array_flip( self::REQUIRED_FIELD_NAMES ) );
		$instance->setField( 'comment', 'changed' );
		$this->assertSame( 'changed', $instance->getField( 'comment' ) );
	}

	public function testSetAndGetNonExistingField() {
		$fields = array_flip( self::REQUIRED_FIELD_NAMES );
		$instance = new TextRevision( $fields );

		$this->expectException( InvalidArgumentException::class );
		$instance->getField( 'invalid' );
	}

	public function testSetNonExistingField() {
		$instance = new TextRevision( array_flip( self::REQUIRED_FIELD_NAMES ) );

		$this->expectException( InvalidArgumentException::class );
		$instance->setField( 'invalid', null );
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
