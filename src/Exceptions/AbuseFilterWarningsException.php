<?php

namespace FileImporter\Exceptions;

use Wikimedia\Message\MessageSpecifier;

/**
 * @license GPL-2.0-or-later
 */
class AbuseFilterWarningsException extends LocalizedImportException {

	/**
	 * @param MessageSpecifier[] $messages
	 */
	public function __construct(
		private readonly array $messages,
	) {
		parent::__construct( 'fileimporter-warningabusefilter' );
	}

	/**
	 * @return MessageSpecifier[]
	 */
	public function getMessages(): array {
		return $this->messages;
	}

}
