<?php

namespace FileImporter\Exceptions;

use File;

/**
 * @license GPL-2.0-or-later
 * @author Addshore
 *
 * @codeCoverageIgnore
 */
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
