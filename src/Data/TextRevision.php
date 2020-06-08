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

	private const ERROR_TEXT_FIELD_MISSING = 'textFieldMissing';

	private const REQUIRED_FIELDS = [
		'minor',
		'user',
		'timestamp',
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
	 *
	 * @throws InvalidArgumentException if incorrect fields are entered
	 */
	public function __construct( array $fields ) {
		$this->throwExceptionIfMissingFields( $fields );
		$this->fields = $fields;
	}

	private function throwExceptionIfMissingFields( array $fields ) {
		$diff = array_diff_key( array_flip( self::REQUIRED_FIELDS ), $fields );
		if ( $diff !== [] ) {
			throw new InvalidArgumentException(
				__CLASS__ . ': Missing ' . key( $diff ) . ' field on construction',
				self::ERROR_TEXT_FIELD_MISSING );
		}
	}

	/**
	 * @param string $name
	 *
	 * @return mixed|null Null if the field isn't known
	 */
	public function getField( $name ) {
		return $this->fields[$name] ?? null;
	}

	/**
	 * @return array
	 */
	public function getFields() {
		return $this->fields;
	}

}
