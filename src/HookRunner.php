<?php

namespace FileImporter;

use MediaWiki\Hook\EditFilterMergedContentHook;
use MediaWiki\Hook\EditPage__showEditForm_initialHook;
use MediaWiki\Hook\UploadStashFileHook;
use MediaWiki\Hook\UploadVerifyUploadHook;
use MediaWiki\HookContainer\HookContainer;

/**
 * This is a hook runner class, see docs/Hooks.md in core.
 * @internal
 * @license GPL-2.0-or-later
 */
class HookRunner implements
	EditFilterMergedContentHook,
	EditPage__showEditForm_initialHook,
	UploadStashFileHook,
	UploadVerifyUploadHook
{

	public function __construct( private readonly HookContainer $hookContainer ) {
	}

	/**
	 * @inheritDoc
	 */
	public function onEditFilterMergedContent( $context, $content, $status,
		$summary, $user, $minoredit
	) {
		return $this->hookContainer->run(
			'EditFilterMergedContent',
			[ $context, $content, $status, $summary, $user, $minoredit ]
		);
	}

	/**
	 * @inheritDoc
	 */
	public function onEditPage__showEditForm_initial( $editor, $out ) {
		return $this->hookContainer->run(
			'EditPage::showEditForm:initial',
			[ $editor, $out ]
		);
	}

	/**
	 * @inheritDoc
	 */
	public function onUploadStashFile( $upload, $user, $props, &$error ) {
		return $this->hookContainer->run(
			'UploadStashFile',
			[ $upload, $user, $props, &$error ]
		);
	}

	/**
	 * @inheritDoc
	 */
	public function onUploadVerifyUpload( $upload, $user, $props, $comment,
		$pageText, &$error
	) {
		return $this->hookContainer->run(
			'UploadVerifyUpload',
			[ $upload, $user, $props, $comment, $pageText, &$error ]
		);
	}

}
