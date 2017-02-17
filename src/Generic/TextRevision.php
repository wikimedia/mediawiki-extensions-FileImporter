<?php

namespace FileImporter\Generic;

use FileImporter\Generic\Exceptions\InvalidArgumentException;

/**
 * This class represents a single revision of text, as recognized by MediaWiki.
 * This data can all be retrieved from the API or the Database and can be used to copy
 * the exact revision onto another site.
 */
class TextRevision {

	private static $fieldNames = [
		// Needed for new DB storage
		'revid', // needed for parent to link to
		'parentid',
		'minor',
		'user',
		'userid',
		'timestamp',
		'size',
		'sha1',
		'contentmodel',
		'contentformat',
		'comment',
		'*',
		// Needed for display on import page
		'parsedomment',
	];

	/**
	 * @var array
	 */
	private $fields;

	/**
	 * @param array $fields
	 * @throws InvalidArgumentException if incorrect fields are entered
	 */
	public function __construct( array $fields ) {
		$this->throwExceptionIfMissingFields( $fields );
		$this->fields = $fields;
	}

	private function throwExceptionIfMissingFields( array $fields ) {
		$keys = array_keys( $fields );
		$expectedKeys = self::$fieldNames;
		if ( sort( $expectedKeys ) !== sort( $keys ) ) {
			throw new InvalidArgumentException( __CLASS__ . ': Missing fields on construction' );
		}
	}

	/**
	 * @param string $name
	 *
	 * @return mixed
	 * @throws InvalidArgumentException if an unrecognized field is requested
	 */
	public function getField( $name ) {
		if ( !in_array( $name, self::$fieldNames ) ) {
			throw new InvalidArgumentException( __CLASS__ . ': Unrecognized field requested' );
		}
		return $this->fields[$name];

	}

}
