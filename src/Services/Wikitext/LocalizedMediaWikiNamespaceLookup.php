<?php

namespace FileImporter\Services\Wikitext;

use Language;

/**
 * A reverse namespace name to ID lookup that depends on MediaWiki core and does *not* recognize
 * canonical (English) namespace names, only localized ones.
 *
 * @license GPL-2.0-or-later
 * @codeCoverageIgnore
 */
class LocalizedMediaWikiNamespaceLookup implements NamespaceNameLookup {

	/** @var Language */
	private $language;

	public function __construct( Language $language ) {
		$this->language = $language;
	}

	/**
	 * @return int|false False if there is no namespace with this name.
	 */
	public function getIndex( string $namespaceName ) {
		return $this->language->getLocalNsIndex( $namespaceName );
	}

}
