<?php

namespace FileImporter\Data;

use Wikimedia\Assert\Assert;

/**
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class FileRevisions {

	/** @var FileRevision[] */
	private array $fileRevisions;
	/** @var int|null */
	private $latestKey = null;

	/**
	 * @param FileRevision[] $fileRevisions
	 */
	public function __construct( array $fileRevisions ) {
		Assert::parameter( $fileRevisions !== [], '$fileRevisions', 'cannot be empty' );
		Assert::parameterElementType( FileRevision::class, $fileRevisions, '$fileRevisions' );
		$this->fileRevisions = $fileRevisions;
	}

	/**
	 * @return FileRevision[]
	 */
	public function toArray(): array {
		return $this->fileRevisions;
	}

	/**
	 * @return FileRevision
	 */
	public function getLatest() {
		$this->latestKey ??= $this->calculateLatestKey();
		return $this->fileRevisions[$this->latestKey];
	}

	private function calculateLatestKey(): int {
		$latestTimestamp = 0;
		$latestKey = null;
		foreach ( $this->fileRevisions as $key => $revision ) {
			if ( $revision->getField( 'archivename' ) ) {
				continue;
			}

			$timestamp = strtotime( $revision->getField( 'timestamp' ) );
			if ( $latestTimestamp < $timestamp ) {
				$latestTimestamp = $timestamp;
				$latestKey = $key;
			}
		}

		Assert::postcondition( $latestKey !== null, 'cannot determine latest file revision' );
		return $latestKey;
	}

}
