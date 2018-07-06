<?php

namespace FileImporter\Services;

use WikiPage;

/**
 * Thin wrapper around the static {@see WikiPage::newFromID} to make code testable.
 *
 * @license GPL-2.0-or-later
 * @author Thiemo Kreuz
 *
 * @codeCoverageIgnore
 */
class WikiPageFactory {

	/**
	 * @param int $pageId
	 *
	 * @return WikiPage
	 */
	public function newFromID( $pageId ) {
		// T181391: Read from master, as the page has only just been created, and in multi-DB setups
		// slaves will have lag.
		return WikiPage::newFromID( $pageId, WikiPage::READ_LATEST );
	}

}
