<?php

namespace FileImporter\Exceptions;

use ILocalizedException;
use Message;
use MessageSpecifier;
use Throwable;

/**
 * Logic has been taken form core class LocalizedException
 * @todo move logic to a trait in core and use it from there?
 *
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class LocalizedImportException extends ImportException implements ILocalizedException {

	/** @var string|array|MessageSpecifier */
	protected $messageSpec;

	/**
	 * @param string|array|MessageSpecifier $messageSpec See Message::newFromSpecifier
	 * @param Throwable|null $previous The previous exception used for the exception chaining.
	 */
	public function __construct( $messageSpec, Throwable $previous = null ) {
		$this->messageSpec = $messageSpec;
		$msg = $this->getMessageObject();
		$code = str_replace( 'fileimporter-', '', $msg->getKey() );

		parent::__construct(
			$msg->inLanguage( 'en' )->useDatabase( false )->text(),
			$code,
			$previous );
	}

	/**
	 * @return Message
	 */
	public function getMessageObject() {
		return Message::newFromSpecifier( $this->messageSpec );
	}

}
