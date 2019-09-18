<?php

namespace FileImporter\Services\Wikitext;

/**
 * Generic interface for any kind of reverse namespace name to ID lookup.
 */
interface NamespaceNameLookup {

	/**
	 * @param string $namespaceName
	 * @return int|false
	 */
	public function getIndex( $namespaceName );

}
