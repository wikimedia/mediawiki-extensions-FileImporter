<?php

namespace FileImporter;

use Html;
use MediaWiki\MediaWikiServices;
use Message;
use OOUI\ButtonInputWidget;
use OOUI\TextInputWidget;
use SpecialPage;

class SpecialImportFile extends SpecialPage {

	public function __construct() {
		parent::__construct( 'ImportFile' );
	}

	public function execute( $subPage ) {
		$out = $this->getOutput();
		$out->setPageTitle( new Message( 'fileimporter-specialpage' ) );
		$out->enableOOUI();

		$rawUrl = $out->getRequest()->getVal( 'clientUrl', '' );

		if ( !$rawUrl ) {
			$this->showUrlEntryPage();
			return;
		}

		$parsedUrl = wfParseUrl( $rawUrl );
		if ( $parsedUrl === false ) {
			$this->showUnparsableUrlMessage( $rawUrl );
			$this->showUrlEntryPage();
		} elseif ( !$this->urlAllowed( $parsedUrl ) ) {
			$this->showDisallowedUrlMessage();
			$this->showUrlEntryPage();
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
		/** @var UrlBasedSiteLookup $lookup */
		$lookup = MediaWikiServices::getInstance()->getService( 'FileImporterUrlBasedSiteLookup' );
		return $lookup->getSite( $parsedUrl ) !== null;
	}

	private function showUnparsableUrlMessage( $rawUrl ) {
		$this->showWarningMessage(
			( new Message( 'fileimporter-cantparseurl' ) )->plain() . ': ' . $rawUrl
		);
	}

	private function showDisallowedUrlMessage() {
		$this->showWarningMessage( ( new Message( 'fileimporter-cantimporturl' ) )->plain() );
	}

	private function showWarningMessage( $message ) {
		$this->getOutput()->addHTML(
			Html::rawElement(
				'div',
				[ 'class' => 'warningbox' ],
				Html::element( 'p', [], $message )
			)
		);
	}

	private function showUrlEntryPage() {
		$this->getOutput()->addModuleStyles( 'ext.FileImporter.Special' );
		$this->getOutput()->addHTML(
			Html::openElement( 'div' ) . Html::openElement(
				'form',
				[
					'action' => $this->getPageTitle()->getLocalURL(),
					'method' => 'GET',
				]
			) . new TextInputWidget(
				[
					'name' => 'clientUrl',
					'classes' => [ 'mw-movtocom-url-text' ],
					'label' => 'Foo',
					'autofocus' => true,
					'required' => true,
					'placeholder' => ( new Message( 'fileimporter-exampleprefix' ) )->plain() .
						': https://en.wikipedia.org/wiki/File:Berlin_Skyline'
				]
			) . new ButtonInputWidget(
				[
					'classes' => [ 'mw-movtocom-url-submit' ],
					'label' => ( new Message( 'fileimporter-submit' ) )->plain(),
					'type' => 'submit',
					'flags' => [ 'primary', 'progressive' ],
				]
			) . Html::closeElement( 'form' ) . Html::closeElement( 'div' )
		);
	}

	/**
	 * @param string[] $parsedUrl return of wfParseUrl
	 */
	private function showImportPage( array $parsedUrl ) {
		$this->getOutput()->addHTML(
			Html::element(
				'p',
				[],
				( new Message( 'fileimporter-importfilefromprefix' ) )->plain()
			) . ': ' . implode( '|', $parsedUrl )
		);
	}

}
