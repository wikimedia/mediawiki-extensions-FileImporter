<?php

namespace FileImporter\Exceptions;

use Exception;
use ILocalizedException;
use Message;
use MessageSpecifier;
use Throwable;

/**
 * Logic has been taken form core class LocalizedException
 * @todo move logic to a trait in core and use it from there?
 */
class LocalizedImportException extends ImportException implements ILocalizedException {

	/** @var string|array|MessageSpecifier */
	protected $messageSpec;

	/**
	 * @param string|array|MessageSpecifier $messageSpec See Message::newFromSpecifier
	 * @param int $code Exception code
	 * @param Exception|Throwable $previous The previous exception used for the exception chaining.
	 */
	public function __construct( $messageSpec, $code = 0, $previous = null ) {
		$this->messageSpec = $messageSpec;

		$msg = $this->getMessageObject()->inLanguage( 'en' )->useDatabase( false )->text();
		parent::__construct( $msg, $code, $previous );
	}

	/**
	 * @return Message
	 */
	public function getMessageObject() {
		return Message::newFromSpecifier( $this->messageSpec );
	}

}
