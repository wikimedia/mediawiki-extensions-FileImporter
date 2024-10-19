<?php

namespace FileImporter\Exceptions;

use Wikimedia\Message\MessageSpecifier;

/**
 * @license GPL-2.0-or-later
 */
class AbuseFilterWarningsException extends LocalizedImportException {

	/** @var MessageSpecifier[] */
	protected array $messages;

	/**
	 * @param MessageSpecifier[] $messages
	 */
	public function __construct( array $messages ) {
		$this->messages = $messages;
		parent::__construct( 'fileimporter-warningabusefilter' );
	}

	/**
	 * @return MessageSpecifier[]
	 */
	public function getMessages(): array {
		return $this->messages;
	}

}
