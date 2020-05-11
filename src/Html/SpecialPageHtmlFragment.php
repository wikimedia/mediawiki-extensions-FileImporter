<?php

namespace FileImporter\Html;

use IContextSource;
use InvalidArgumentException;
use Language;
use Message;
use MessageLocalizer;
use OutputPage;
use SpecialPage;
use Title;
use User;

/**
 * Common framework for classes providing HTML fragments (similar to
 * {@see https://developer.mozilla.org/de/docs/Web/API/Document/createDocumentFragment}) that are
 * meant to be shown on a special page. Implementations should have a `getHtml` method with as many
 * arguments as needed.
 *
 * @license GPL-2.0-or-later
 * @author Thiemo Kreuz
 */
abstract class SpecialPageHtmlFragment implements MessageLocalizer {

	/**
	 * @var SpecialPage
	 */
	private $specialPage;

	/**
	 * Implementations should not have a constructor, but provide whatever is needed as arguments to
	 * their `getHtml` method.
	 *
	 * @param SpecialPage|self $specialPage
	 */
	final public function __construct( $specialPage ) {
		if ( $specialPage instanceof self ) {
			$specialPage = $specialPage->specialPage;
		} elseif ( !( $specialPage instanceof SpecialPage ) ) {
			throw new InvalidArgumentException(
				'$specialPage must be a SpecialPage or SpecialPageHtmlFragment' );
		}

		$this->specialPage = $specialPage;
	}

	/**
	 * @return Title
	 */
	protected function getPageTitle() {
		return $this->specialPage->getPageTitle();
	}

	/**
	 * @return IContextSource
	 */
	protected function getContext() {
		return $this->specialPage->getContext();
	}

	/**
	 * @return OutputPage
	 */
	protected function getOutput() {
		return $this->specialPage->getOutput();
	}

	/**
	 * @return User
	 */
	protected function getUser() {
		return $this->specialPage->getUser();
	}

	/**
	 * @return Language
	 */
	protected function getLanguage() {
		return $this->specialPage->getLanguage();
	}

	/**
	 * @see MessageLocalizer::msg
	 *
	 * @param string|string[]|\MessageSpecifier $key
	 * @param mixed ...$params Any number of message parameters
	 *
	 * @return Message
	 */
	public function msg( $key, ...$params ) {
		return $this->specialPage->msg( $key, ...$params );
	}

}
