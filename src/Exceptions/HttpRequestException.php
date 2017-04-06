<?php

namespace FileImporter\Exceptions;

use StatusValue;

/**
 * Thrown in cases where a HttpRequest has failed.
 */
class HttpRequestException extends ImportException {

	private $statusValue;
	private $statusCode;

	/**
	 * @param StatusValue $statusValue
	 * @param int $statusCode
	 */
	public function __construct( StatusValue $statusValue, $statusCode ) {
		$this->statusValue = $statusValue;
		$this->statusCode = $statusCode;
		parent::__construct();
	}

	public function getStatusValue() {
		return $this->statusValue;
	}

	public function getStatusCode() {
		return $this->statusCode;
	}

}
