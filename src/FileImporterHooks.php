<?php

namespace FileImporter;

use FileImporter\Html\ImportSuccessSnippet;
use MediaWiki\Actions\ActionEntryPoint;
use MediaWiki\ChangeTags\Hook\ChangeTagsListActiveHook;
use MediaWiki\ChangeTags\Hook\ListDefinedTagsHook;
use MediaWiki\Config\Config;
use MediaWiki\Hook\BeforeInitializeHook;
use MediaWiki\Output\OutputPage;
use MediaWiki\Request\WebRequest;
use MediaWiki\Title\Title;
use MediaWiki\User\Hook\UserGetReservedNamesHook;
use MediaWiki\User\User;

/**
 * @license GPL-2.0-or-later
 * @author Andrew Kostka
 */
class FileImporterHooks implements
	BeforeInitializeHook,
	ChangeTagsListActiveHook,
	ListDefinedTagsHook,
	UserGetReservedNamesHook
{

	public function __construct(
		private readonly Config $config
	) {
	}

	/**
	 * Show an import success message when appropriate.
	 *
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/BeforeInitialize
	 *
	 * @param Title $title
	 * @param null $unused
	 * @param OutputPage $output
	 * @param User $user
	 * @param WebRequest $request
	 * @param ActionEntryPoint $mediaWiki
	 */
	public function onBeforeInitialize(
		$title,
		$unused,
		$output,
		$user,
		$request,
		$mediaWiki
	) {
		if ( $request->getVal( ImportSuccessSnippet::NOTICE_URL_KEY ) === null
			|| !$title->inNamespace( NS_FILE )
			|| !$title->exists()
			|| !$user->isNamed()
		) {
			return;
		}

		$output->enableOOUI();
		$output->prependHTML(
			( new ImportSuccessSnippet() )->getHtml(
				$output->getContext(), $title, $user ) );
	}

	/**
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ChangeTagsListActive
	 *
	 * @param string[] &$tags
	 */
	public function onChangeTagsListActive( &$tags ) {
		$this->onListDefinedTags( $tags );
	}

	/**
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ListDefinedTags
	 *
	 * @param string[] &$tags
	 */
	public function onListDefinedTags( &$tags ) {
		$tags[] = 'fileimporter';
		$tags[] = 'fileimporter-imported';
	}

	/**
	 * Add FileImporter username to the list of reserved ones for
	 * replacing suppressed usernames in certain revisions
	 *
	 * @param string[] &$reservedUsernames
	 */
	public function onUserGetReservedNames( &$reservedUsernames ) {
		$reservedUsernames[] = $this->config->get( 'FileImporterAccountForSuppressedUsername' );
	}

}
