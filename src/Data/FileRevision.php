<?php

namespace FileImporter\Data;

use FileImporter\Exceptions\InvalidArgumentException;

/**
 * This class represents a single revision of a files, as recognized by MediaWiki.
 * This data can all be retrieved from the API or the Database and can be used to copy
 * the exact revision onto another site.
 *
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class FileRevision {

	private const ERROR_MISSING_FIELD = 'revisionMissingField';
	private const ERROR_UNKNOWN_FIELD = 'revisionUnknownField';

	private static $fieldNames = [
		'name',
		'description',
		'user',
		'timestamp',
		'size',
		'thumburl',
		'url',
	];

	/**
	 * @var array
	 */
	private $fields;

	/**
	 * @param array $fields
	 *
	 * @throws InvalidArgumentException if incorrect fields are entered
	 */
	public function __construct( array $fields ) {
		$this->throwExceptionIfMissingFields( $fields );
		$this->fields = $fields + [ 'sha1' => '' ];
	}

	private function throwExceptionIfMissingFields( array $fields ) {
		$diff = array_diff_key( array_flip( self::$fieldNames ), $fields );
		if ( $diff !== [] ) {
			throw new InvalidArgumentException(
				__CLASS__ . ': Missing ' . key( $diff ) . ' field on construction',
				self::ERROR_MISSING_FIELD );
		}
	}

	/**
	 * @param string $name
	 *
	 * @return mixed
	 * @throws InvalidArgumentException if an unrecognized field is requested
	 */
	public function getField( $name ) {
		if ( !array_key_exists( $name, $this->fields ) ) {
			throw new InvalidArgumentException(
				__CLASS__ . ': Unrecognized field requested', self::ERROR_UNKNOWN_FIELD );
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
