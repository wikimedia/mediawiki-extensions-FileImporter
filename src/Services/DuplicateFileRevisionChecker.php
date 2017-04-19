<?php

namespace FileImporter\Services;

use File;
use FileImporter\Data\FileRevision;
use LocalRepo;
use Wikimedia\Assert\Assert;

/**
 * Class that can be used to check if a FileRevision already exists on the current wiki.
 * Only current / latest and non deleted files are checked.
 */
class DuplicateFileRevisionChecker {

	/**
	 * @var LocalRepo
	 */
	private $localRepo;

	public function __construct( LocalRepo $localRepo ) {
		$this->localRepo = $localRepo;
	}

	/**
	 * @param FileRevision $fileRevision
	 *
	 * @return File[] array of matched files
	 */
	public function findDuplicates( FileRevision $fileRevision ) {
		$files = $this->localRepo->findBySha1( $fileRevision->getField( 'sha1' ) );
		$files = $this->removeIgnoredFiles( $files );

		return $files;
	}

	/**
	 * This removed removes files that are either old or deleted.
	 *
	 * @param File[] $files
	 *
	 * @return File[]
	 */
	private function removeIgnoredFiles( array $files ) {
		$wantedFiles = [];

		foreach ( $files as $file ) {
			if (
				$file->isOld() ||
				$file->isDeleted( File::DELETED_FILE )
			) {
				continue;
			}
			$wantedFiles[] = $file;
		}

		return $wantedFiles;
	}

}
