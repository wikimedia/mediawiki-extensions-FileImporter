<?php

namespace FileImporter\Data;

use FileImporter\Exceptions\InvalidArgumentException;

/**
 * This class represents a single revision of a files, as recognized by MediaWiki.
 * This data can all be retrieved from the API or the Database and can be used to copy
 * the exact revision onto another site.
 */
class FileRevision {

	private static $fieldNames = [
		// Needed for new DB storage
		'name',
		'size',
		'width',
		'height',
		'metadata',
		'bits',
		//'media_type', // needed in the DB but derived from the file itself?
		//'major_mime', // needed in the DB but derived from the file itself?
		//'minor_mime', // needed in the DB but derived from the file itself?
		'description',
		'user',
		'user_text',
		'timestamp',
		'sha1',
		//'type', // needed in the DB but derived from the file itself?
		// Needed for display on import page
		'thumburl',
		// Needed for HTTP download
		'url',
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
		// TODO check sha1 is correct / base36
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
