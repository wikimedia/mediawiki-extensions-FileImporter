<?php

namespace FileImporter\Data;

use FileImporter\Exceptions\InvalidArgumentException;

/**
 * This class represents a single revision of text, as recognized by MediaWiki.
 * This data can all be retrieved from the API or the Database and can be used to copy
 * the exact revision onto another site.
 *
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class TextRevision {

	const ERROR_TEXT_FIELD_MISSING = 'textFieldMissing';
	const ERROR_TEXT_FIELD_UNKNOWN = 'textFieldUnknown';

	private static $fieldNames = [
		'minor',
		'user',
		'timestamp',
		'sha1',
		'contentmodel',
		'contentformat',
		'comment',
		'*',
		'title',
		'tags',
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
		$diff = array_diff_key( array_flip( self::$fieldNames ), $fields );
		if ( $diff !== [] ) {
			throw new InvalidArgumentException(
				__CLASS__ . ': Missing ' . key( $diff ) . ' field on construction',
				self::ERROR_TEXT_FIELD_MISSING );
		}
	}

	/**
	 * @param string $name
	 * @param mixed $value
	 *
	 * @throws InvalidArgumentException if an unrecognized field is requested
	 */
	public function setField( $name, $value ) {
		if ( !in_array( $name, self::$fieldNames ) ) {
			throw new InvalidArgumentException( __CLASS__ . ': Unrecognized field requested',
				self::ERROR_TEXT_FIELD_UNKNOWN );
		}
		$this->fields[$name] = $value;
	}

	/**
	 * @param string $name
	 *
	 * @return mixed
	 * @throws InvalidArgumentException if an unrecognized field is requested
	 */
	public function getField( $name ) {
		if ( !in_array( $name, self::$fieldNames ) ) {
			throw new InvalidArgumentException( __CLASS__ . ': Unrecognized field requested',
				self::ERROR_TEXT_FIELD_UNKNOWN );
		}
		return $this->fields[$name];
	}

	/**
	 * @return array
	 */
	public function getFields() {
		return $this->fields;
	}

}
