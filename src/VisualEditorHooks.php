<?php

namespace FileImporter;

use MediaWiki\Extension\VisualEditor\VisualEditorBeforeEditorHook;
use MediaWiki\Title\Title;
use OutputPage;
use Skin;

/**
 * All hooks from the VisualEditor extension which is optional to use with this extension.
 * @license GPL-2.0-or-later
 */
class VisualEditorHooks implements VisualEditorBeforeEditorHook {

	/**
	 * Same parameters as {@see \MediaWiki\Hook\BeforePageDisplayHook}.
	 *
	 * @param OutputPage $output
	 * @param Skin $skin
	 * @return bool
	 */
	public function onVisualEditorBeforeEditor( OutputPage $output, Skin $skin ): bool {
		// The context gets changed to be that of a file page in WikiEditor::runEditFormInitialHook
		// so re-construct the original title from the request.
		$requestTitle = Title::newFromText( $output->getRequest()->getVal( 'title' ) );
		return !$requestTitle || !$requestTitle->isSpecial( 'ImportFile' );
	}

}
