<?php

namespace FileImporter\Services\Wikitext;

use InvalidArgumentException;
use Language;

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
	 */
	public function __construct( $languageCode ) {
		if ( !is_string( $languageCode ) ) {
			throw new InvalidArgumentException( '$languageCode must be a string' );
		}

		$this->language = Language::factory( $languageCode );
	}

	/**
	 * @param string $namespaceName
	 * @return int|false
	 */
	public function getIndex( $namespaceName ) {
		return $this->language ? $this->language->getLocalNsIndex( $namespaceName ) : false;
	}

}
