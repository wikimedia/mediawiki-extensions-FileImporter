<?php

namespace FileImporter\Exceptions;

use MWHttpRequest;
use StatusValue;

/**
 * Thrown in cases where a HttpRequest has failed.
 */
class HttpRequestException extends ImportException {

	private $statusValue;
	private $httpRequest;

	/**
	 * @param StatusValue $statusValue
	 * @param MWHttpRequest $httpRequest
	 */
	public function __construct( StatusValue $statusValue, MWHttpRequest $httpRequest ) {
		$this->statusValue = $statusValue;
		$this->httpRequest = $httpRequest;
		parent::__construct();
	}

	public function getStatusValue() {
		return $this->statusValue;
	}

	public function getHttpRequest() {
		return $this->httpRequest;
	}

}
