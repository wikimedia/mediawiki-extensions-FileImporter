<?php

namespace FileImporter\Exceptions;

use MediaWiki\FileRepo\File\File;

/**
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class DuplicateFilesException extends ImportException {

	private const ERROR_CODE = 'duplicateFiles';

	/**
	 * @param File[] $duplicateFiles
	 */
	public function __construct(
		private readonly array $duplicateFiles,
	) {
		parent::__construct( 'File already on wiki', self::ERROR_CODE );
	}

	/**
	 * @return File[]
	 */
	public function getFiles(): array {
		return $this->duplicateFiles;
	}

}
