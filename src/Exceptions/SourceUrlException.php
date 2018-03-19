<?php

namespace FileImporter\Exceptions;

/**
 * Thrown in cases that the SourceUrl is not deemed to be acceptable.
 *
 * @license GPL-2.0-or-later
 * @author Addshore
 *
 * @codeCoverageIgnore
 */
class SourceUrlException extends LocalizedImportException {

	public function __construct() {
		parent::__construct( 'fileimporter-cantimporturl' );
	}

}
