<?php

namespace FileImporter\Services\Wikitext;

/**
 * Generic interface for any kind of reverse namespace name to ID lookup.
 *
 * @license GPL-2.0-or-later
 */
interface NamespaceNameLookup {

	/**
	 * @param string $namespaceName
	 * @return int|false False if there is no namespace with this name.
	 */
	public function getIndex( $namespaceName );

}
