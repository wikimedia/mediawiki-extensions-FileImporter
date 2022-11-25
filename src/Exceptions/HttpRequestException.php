<?php

namespace FileImporter\Exceptions;

use MWHttpRequest;
use StatusValue;

/**
 * Thrown in cases where a HttpRequest has failed.
 *
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class HttpRequestException extends ImportException {

	/** @var StatusValue */
	private $statusValue;
	/** @var MWHttpRequest */
	private $httpRequest;

	/**
	 * @param StatusValue $statusValue
	 * @param MWHttpRequest $httpRequest
	 */
	public function __construct( StatusValue $statusValue, MWHttpRequest $httpRequest ) {
		$this->statusValue = $statusValue;
		$this->httpRequest = $httpRequest;

		parent::__construct( (string)$statusValue, $httpRequest->getStatus() );
	}

	/**
	 * @return StatusValue
	 */
	public function getStatusValue() {
		return $this->statusValue;
	}

	/**
	 * @return MWHttpRequest
	 */
	public function getHttpRequest() {
		return $this->httpRequest;
	}

}
