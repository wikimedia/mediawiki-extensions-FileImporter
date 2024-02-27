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

	private const REQUIRED_FIELDS = [
		'name',
		'description',
		'user',
		'timestamp',
		'size',
		'thumburl',
		'url',
	];

	/** @var array */
	private $fields;

	/**
	 * @throws InvalidArgumentException if incorrect fields are entered
	 */
	public function __construct( array $fields ) {
		$this->throwExceptionIfMissingFields( $fields );
		$this->fields = $fields;
	}

	private function throwExceptionIfMissingFields( array $fields ): void {
		$diff = array_diff_key( array_flip( self::REQUIRED_FIELDS ), $fields );
		if ( $diff !== [] ) {
			throw new InvalidArgumentException(
				__CLASS__ . ': Missing ' . key( $diff ) . ' field on construction',
				self::ERROR_MISSING_FIELD );
		}
	}

	/**
	 * @return mixed|null Null if the field isn't known
	 */
	public function getField( string $name ) {
		return $this->fields[$name] ?? null;
	}

	public function getFields(): array {
		return $this->fields;
	}

}
