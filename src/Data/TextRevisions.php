<?php

namespace FileImporter\Data;

use Wikimedia\Assert\Assert;

/**
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class TextRevisions {

	/** @var int|null */
	private $latestKey = null;

	/**
	 * @param TextRevision[] $textRevisions
	 */
	public function __construct(
		private readonly array $textRevisions,
	) {
		Assert::parameter( $textRevisions !== [], '$textRevisions', 'cannot be empty' );
		Assert::parameterElementType( TextRevision::class, $textRevisions, '$textRevisions' );
	}

	/**
	 * @return TextRevision[]
	 */
	public function toArray(): array {
		return $this->textRevisions;
	}

	/**
	 * @return TextRevision|null
	 */
	public function getLatest() {
		$this->latestKey ??= $this->calculateLatestKey();
		return $this->latestKey !== null ? $this->textRevisions[$this->latestKey] : null;
	}

	private function calculateLatestKey(): ?int {
		$latestTimestamp = 0;
		$latestKey = null;
		foreach ( $this->textRevisions as $key => $revision ) {
			$timestamp = strtotime( $revision->getField( 'timestamp' ) );
			if ( $latestTimestamp < $timestamp ) {
				$latestTimestamp = $timestamp;
				$latestKey = $key;
			}
		}
		return $latestKey;
	}

}
