<?php

namespace FileImporter\Services\Wikitext;

use InvalidArgumentException;
use Language;
use MWException;

/**
 * A reverse namespace name to ID lookup that depends on MediaWiki core and does *not* recognize
 * canonical (English) namespace names, only localized ones.
 */
class LocalizedMediaWikiNamespaceLookup implements NamespaceNameLookup {

	/**
	 * @var Language
	 */
	private $language;

	/**
	 * @param string $languageCode
	 * @throws InvalidArgumentException if $languageCode is not a string
	 * @throws MWException if the language code is invalid
	 */
	public function __construct( $languageCode ) {
		if ( !is_string( $languageCode ) ) {
			throw new InvalidArgumentException( '$languageCode must be a string' );
		}

		$this->language = Language::factory( $languageCode );
	}

	/**
	 * @param string $namespaceName
	 * @return int|false False if there is no namespace with this name.
	 */
	public function getIndex( $namespaceName ) {
		return $this->language->getLocalNsIndex( $namespaceName );
	}

}
