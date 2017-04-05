<?php

namespace FileImporter\Generic\Data;

use Wikimedia\Assert\Assert;

class TextRevisions {

	/**
	 * @var TextRevision[]
	 */
	private $textRevisions;

	private $latestKey = null;

	/**
	 * @param TextRevision[] $textRevisions
	 */
	public function __construct( array $textRevisions ) {
		Assert::parameterElementType( TextRevision::class, $textRevisions, '$textRevisions' );
		$this->textRevisions = $textRevisions;
	}

	/**
	 * @return TextRevision[]
	 */
	public function toArray() {
		return $this->textRevisions;
	}

	/**
	 * @return TextRevision|null
	 */
	public function getLatest() {
		if ( $this->latestKey === null ) {
			$this->calculateLatestKey();
		}

		return $this->latestKey !== null ? $this->textRevisions[$this->latestKey] : null;
	}

	private function calculateLatestKey() {
		$latestTimestamp = 0;
		foreach ( $this->textRevisions as $key => $revision ) {
			$timestamp = strtotime( $revision->getField( 'timestamp' ) );
			if ( $latestTimestamp < $timestamp ) {
				$latestTimestamp = $timestamp;
				$this->latestKey = $key;
			}
		}
	}

}
