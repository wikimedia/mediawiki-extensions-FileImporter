<?php

namespace FileImporter;

use SpecialPage;

class SpecialImportFile extends SpecialPage {

	public function __construct() {
		parent::__construct( 'FileImport' );
	}

	public function execute( $subPage ) {
		$out = $this->getOutput();
		$out->setPageTitle( new \Message( 'fileimport-specialpage' ) );

		$rawUrl = $out->getRequest()->getVal( 'clientUrl' );
		$parsedUrl = wfParseUrl( $rawUrl );
		if ( $parsedUrl === false ) {
			$this->showUrlEntryPage( $rawUrl );
		} elseif ( !$this->urlAllowed( $parsedUrl ) ) {
			$this->showUrlNotAllowedPage();
		} else {
			$this->showImportPage( $parsedUrl );
		}
	}

	/**
	 * @param string[] $parsedUrl return of wfParseUrl
	 *
	 * @return bool
	 */
	private function urlAllowed( array $parsedUrl ) {
		// TODO decide if this URL is allowed. ie, is it a wikimedia project & a mediawiki wiki
		return true;
	}

	/**
	 * @param string $rawUrl
	 */
	private function showUrlEntryPage( $rawUrl ) {
		// TODO show a simple special page containing 1 input box that a URL can be pasted into
	}

	private function showUrlNotAllowedPage() {
		// TODO show an error page stating that the URL entered is not allowed for whatever reason!
	}

	/**
	 * @param string[] $parsedUrl return of wfParseUrl
	 */
	private function showImportPage( array $parsedUrl ) {
		// TODO show the details of the import
	}

}
