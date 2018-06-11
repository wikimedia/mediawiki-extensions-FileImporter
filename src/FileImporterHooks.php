<?php

namespace FileImporter;

use MediaWiki\MediaWikiServices;

/**
 * @license GPL-2.0-or-later
 * @author Andrew Kostka
 */
class FileImporterHooks {

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
