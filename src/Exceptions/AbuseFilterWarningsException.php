<?php

namespace FileImporter\Exceptions;

/**
 * @license GPL-2.0-or-later
 */
class AbuseFilterWarningsException extends LocalizedImportException {

	/**
	 * @var array
	 */
	protected $messages;

	/**
	 * @param array $messages
	 */
	public function __construct( $messages ) {
		$this->messages = $messages;
		parent::__construct( 'fileimporter-warningabusefilter' );
	}

	/**
	 * @return array
	 */
	public function getMessages() {
		return $this->messages;
	}

}
