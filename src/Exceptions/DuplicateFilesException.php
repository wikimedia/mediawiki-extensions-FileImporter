<?php

namespace FileImporter\Exceptions;

use File;

/**
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class DuplicateFilesException extends ImportException {

	private const ERROR_CODE = 'duplicateFiles';

	/** @var File[] */
	private $files;

	/**
	 * @param File[] $duplicateFiles
	 */
	public function __construct( array $duplicateFiles ) {
		$this->files = $duplicateFiles;

		parent::__construct( 'File already on wiki', self::ERROR_CODE );
	}

	/**
	 * @return File[]
	 */
	public function getFiles() {
		return $this->files;
	}

}
