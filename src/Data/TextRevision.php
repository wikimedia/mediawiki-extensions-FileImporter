<?php

namespace FileImporter\Data;

use FileImporter\Exceptions\InvalidArgumentException;
use MediaWiki\Revision\SlotRecord;

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
		'comment',
		'slots',
		'title',
		'tags',
	];

	/**
	 * @throws InvalidArgumentException if incorrect fields are entered
	 */
	public function __construct(
		private readonly array $fields,
	) {
		$this->throwExceptionIfMissingFields( $fields );
	}

	private function throwExceptionIfMissingFields( array $fields ): void {
		$diff = array_diff_key( array_flip( self::REQUIRED_FIELDS ), $fields );
		if ( $diff !== [] ) {
			throw new InvalidArgumentException(
				__CLASS__ . ': Missing ' . key( $diff ) . ' field on construction',
				self::ERROR_TEXT_FIELD_MISSING );
		}
	}

	/**
	 * @return mixed|null Null if the field isn't known
	 */
	public function getField( string $name ) {
		return $this->fields[$name] ?? null;
	}

	public function getContent(): string {
		// Old, incomplete database entries result in slots with no content but marked as "missing"
		// or "badcontentformat", {@see ApiQueryRevisionsBase::extractAllSlotInfo}. FileImporter's
		// general philosophy is to be ok with missing text, but not with missing files.
		return $this->fields['slots'][SlotRecord::MAIN]['content'] ?? '';
	}

	public function getContentFormat(): string {
		// We know old, incomplete database entries can't be anything but wikitext
		return $this->fields['slots'][SlotRecord::MAIN]['contentformat'] ?? CONTENT_FORMAT_WIKITEXT;
	}

	public function getContentModel(): string {
		// We know old, incomplete database entries can't be anything but wikitext
		return $this->fields['slots'][SlotRecord::MAIN]['contentmodel'] ?? CONTENT_MODEL_WIKITEXT;
	}

	/**
	 * @internal for debugging only
	 */
	public function getFields(): array {
		return $this->fields;
	}

}
