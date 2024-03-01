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

	private StatusValue $statusValue;
	private MWHttpRequest $httpRequest;

	public function __construct( StatusValue $statusValue, MWHttpRequest $httpRequest ) {
		$this->statusValue = $statusValue;
		$this->httpRequest = $httpRequest;

		parent::__construct( (string)$statusValue, $httpRequest->getStatus() );
	}

	public function getStatusValue(): StatusValue {
		return $this->statusValue;
	}

	public function getHttpRequest(): MWHttpRequest {
		return $this->httpRequest;
	}

}
