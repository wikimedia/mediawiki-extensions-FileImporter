<?php

namespace FileImporter\Services;

use File;
use FileImporter\Data\FileRevision;
use FileRepo;

/**
 * Class that can be used to check if a FileRevision already exists on the current wiki.
 * Only current / latest and non deleted files are checked.
 *
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class DuplicateFileRevisionChecker {

	/**
	 * @var FileRepo
	 */
	private $fileRepo;

	/**
	 * @param FileRepo $fileRepo
	 */
	public function __construct( FileRepo $fileRepo ) {
		$this->fileRepo = $fileRepo;
	}

	/**
	 * @param FileRevision $fileRevision
	 *
	 * @return File[] array of matched files
	 */
	public function findDuplicates( FileRevision $fileRevision ) {
		$sha1 = $fileRevision->getField( 'sha1' );
		if ( !$sha1 ) {
			return [];
		}

		$files = $this->fileRepo->findBySha1( $sha1 );
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
