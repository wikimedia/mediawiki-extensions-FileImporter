<?php

namespace FileImporter;

use MediaWiki\MediaWikiServices;

/**
 * @license GPL-2.0-or-later
 * @author Andrew Kostka
 */
class FileImporterHooks {

	/**
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ChangeTagsListActive
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ListDefinedTags
	 *
	 * @param string[] &$tags
	 */
	public static function onListDefinedTags( array &$tags ) {
		$tags[] = 'fileimporter';
	}

	/**
	 * Add FileImporter username to the list of reserved ones for
	 * replacing suppressed usernames in certain revisions
	 *
	 * @param string[] &$reservedUsernames
	 */
	public static function onUserGetReservedNames( array &$reservedUsernames ) {
		$config = MediaWikiServices::getInstance()->getMainConfig();
		$reservedUsernames[] = $config->get( 'FileImporterAccountForSuppressedUsername' );
	}

}
