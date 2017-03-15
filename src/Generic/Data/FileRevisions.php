<?php

namespace FileImporter\Generic\Data;

use Wikimedia\Assert\Assert;

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
		foreach ( $this->fileRevisions as $key => $fileRevision ) {
			$fileTimestamp = strtotime( $fileRevision->getField( 'timestamp' ) );
			if ( $latestTimestamp < $fileTimestamp ) {
				$latestTimestamp = $fileTimestamp;
				$this->latestKey = $key;
			}
		}
	}

}
