<?php

namespace FileImporter\Exceptions;

use RuntimeException;
use Throwable;

/**
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class ImportException extends RuntimeException {

	/**
	 * @param string $message
	 * @param string $code
	 * @param Throwable|null $previous
	 */
	public function __construct( $message, $code, Throwable $previous = null ) {
		// Unfortunately the code can't be passed through the constructor due to type hints.
		parent::__construct( $message, 0, $previous );
		$this->code = $code;
	}

}
