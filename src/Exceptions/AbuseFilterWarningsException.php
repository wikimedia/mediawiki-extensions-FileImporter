<?php

namespace FileImporter\Exceptions;

use MessageSpecifier;

/**
 * @license GPL-2.0-or-later
 */
class AbuseFilterWarningsException extends LocalizedImportException {

	/**
	 * @var MessageSpecifier[]
	 */
	protected $messages;

	/**
	 * @param MessageSpecifier[] $messages
	 */
	public function __construct( $messages ) {
		$this->messages = $messages;
		parent::__construct( 'fileimporter-warningabusefilter' );
	}

	/**
	 * @return MessageSpecifier[]
	 */
	public function getMessages() {
		return $this->messages;
	}

}
