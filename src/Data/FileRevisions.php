<?php

namespace FileImporter\Data;

use Wikimedia\Assert\Assert;

/**
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class FileRevisions {

	/**
	 * @var FileRevision[]
	 */
	private $fileRevisions;

	private $latestKey = null;

	/**
	 * @param FileRevision[] $fileRevisions
	 */
	public function __construct( array $fileRevisions ) {
		Assert::parameterElementType( FileRevision::class, $fileRevisions, '$fileRevisions' );
		$this->fileRevisions = $fileRevisions;
	}

	/**
	 * @return FileRevision[]
	 */
	public function toArray() {
		return $this->fileRevisions;
	}

	/**
	 * @return FileRevision|null
	 */
	public function getLatest() {
		if ( $this->latestKey === null ) {
			$this->calculateLatestKey();
		}

		return $this->latestKey !== null ? $this->fileRevisions[$this->latestKey] : null;
	}

	private function calculateLatestKey() {
		$latestTimestamp = 0;
		foreach ( $this->fileRevisions as $key => $revision ) {
			$timestamp = strtotime( $revision->getField( 'timestamp' ) );
			if ( $latestTimestamp < $timestamp ) {
				$latestTimestamp = $timestamp;
				$this->latestKey = $key;
			}
		}
	}

}
