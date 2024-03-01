<?php

namespace FileImporter\Html;

use Language;
use MediaWiki\Context\IContextSource;
use MediaWiki\Context\MutableContext;
use MediaWiki\Output\OutputPage;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use Message;
use MessageLocalizer;

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

	private SpecialPage $specialPage;

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
			throw new \InvalidArgumentException(
				'$specialPage must be a SpecialPage or SpecialPageHtmlFragment' );
		}

		$this->specialPage = $specialPage;
	}

	protected function getPageTitle(): Title {
		return $this->specialPage->getPageTitle();
	}

	/**
	 * @return IContextSource|MutableContext
	 */
	protected function getContext() {
		return $this->specialPage->getContext();
	}

	protected function getOutput(): OutputPage {
		return $this->getContext()->getOutput();
	}

	protected function getUser(): User {
		return $this->getContext()->getUser();
	}

	/**
	 * @return Language
	 */
	protected function getLanguage() {
		return $this->getContext()->getLanguage();
	}

	/**
	 * @see MessageLocalizer::msg
	 *
	 * @param string|string[]|\MessageSpecifier $key
	 * @param mixed ...$params Any number of message parameters
	 */
	public function msg( $key, ...$params ): Message {
		return $this->getContext()->msg( $key, ...$params );
	}

}
