<?php

namespace FileImporter\Exceptions;

use File;

class DuplicateFilesException extends ImportException {

	private $files;

	/**
	 * @param File[] $duplicateFiles
	 */
	public function __construct( array $duplicateFiles ) {
		$this->files = $duplicateFiles;
		parent::__construct();
	}

	public function getFiles() {
		return $this->files;
	}

}
