<?php

namespace FileImporter;

use FileImporter\Html\ImportSuccessSnippet;
use MediaWiki;
use MediaWiki\MediaWikiServices;
use OutputPage;
use Title;
use User;
use WebRequest;

/**
 * @license GPL-2.0-or-later
 * @author Andrew Kostka
 */
class FileImporterHooks {

	/**
	 * Show an import success message when appropriate.
	 *
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/BeforeInitialize
	 * @param Title &$title
	 * @param null &$unused
	 * @param OutputPage &$output
	 * @param User &$user
	 * @param WebRequest $request
	 * @param MediaWiki $mediaWiki
	 */
	public static function onBeforeInitialize( Title &$title, &$unused,
		OutputPage &$output, User &$user, WebRequest $request, MediaWiki $mediaWiki
	) {
		if ( $request->getVal( ImportSuccessSnippet::NOTICE_URL_KEY ) === null
			|| $title->getNamespace() !== NS_FILE
			|| !$title->exists()
			|| $user->isAnon()
		) {
			return;
		}

		$output->addModuleStyles( 'ext.FileImporter.Success' );
		$output->prependHTML(
			( new ImportSuccessSnippet() )->getHtml(
				$output->getContext(), $title ) );
	}

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
