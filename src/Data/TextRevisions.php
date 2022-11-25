<?php

namespace FileImporter\Data;

use Wikimedia\Assert\Assert;

/**
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class TextRevisions {

	/** @var TextRevision[] */
	private $textRevisions;
	/** @var int|null */
	private $latestKey = null;

	/**
	 * @param TextRevision[] $textRevisions
	 */
	public function __construct( array $textRevisions ) {
		Assert::parameter( $textRevisions !== [], '$textRevisions', 'cannot be empty' );
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
