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

	public function __construct(
		private readonly StatusValue $statusValue,
		private readonly MWHttpRequest $httpRequest,
	) {
		parent::__construct( (string)$statusValue, $httpRequest->getStatus() );
	}

	public function getStatusValue(): StatusValue {
		return $this->statusValue;
	}

	public function getHttpRequest(): MWHttpRequest {
		return $this->httpRequest;
	}

}
