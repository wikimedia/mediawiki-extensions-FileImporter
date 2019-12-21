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
	 * @param int|string $code
	 * @param Throwable|null $previous
	 */
	public function __construct( $message, $code, Throwable $previous = null ) {
		parent::__construct( $message, (int)$code, $previous );

		// Not all codes can be passed through the constructor due to type hints
		if ( !is_int( $code ) && !ctype_digit( $code ) ) {
			$this->code = $code;
		}
	}

}
