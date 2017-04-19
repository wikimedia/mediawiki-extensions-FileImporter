<?php

namespace FileImporter\Data;

use FileImporter\Exceptions\InvalidArgumentException;

/**
 * This class represents a single revision of text, as recognized by MediaWiki.
 * This data can all be retrieved from the API or the Database and can be used to copy
 * the exact revision onto another site.
 */
class TextRevision {

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
		foreach ( self::$fieldNames as $expectedKey ) {
			if ( !array_key_exists( $expectedKey, $fields ) ) {
				throw new InvalidArgumentException(
					__CLASS__ . ': Missing ' . $expectedKey . ' field on construction'
				);
			}
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
