<?php

namespace FileImporter;

use Html;
use Linker;
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
		$wasPosted = $out->getRequest()->wasPosted();

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
			if ( $wasPosted ) {
				$this->doImport();
			} else {
				$this->showImportPage( $rawUrl );
			}
		}
	}

	private function doImport() {
		// TODO implement importing
		$this->getOutput()->addHTML( 'TODO do the import' );
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
		$this->showInputForm();
	}

	/**
	 * @param string $rawUrl
	 */
	private function showImportPage( $rawUrl ) {
		// TODO actually make the correct file?
		$file = new ExternalMediaWikiFile();
		$out = $this->getOutput();

		$this->getOutput()->addModuleStyles( 'ext.FileImporter.Special' );
		$this->showInputForm( $file->getTargetUrl() );

		$out->addHTML(
			Html::rawElement(
				'p',
				[],
				( new Message( 'fileimporter-importfilefromprefix' ) )->plain() . ': ' .
				Linker::makeExternalLink( $file->getTargetUrl(), $file->getTargetUrl() )
			)
		);

		$out->addHTML( Html::element( 'p', [], $file->getTitle() ) );
		$out->addHTML( Linker::makeExternalImage( $file->getImageUrl(), $file->getTitle() ) );
	}

	private function showInputForm( $clientUrl = null ) {
		$out = $this->getOutput();

		$out->addHTML(
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
					'autofocus' => true,
					'required' => true,
					'type' => 'url',
					'value' => $clientUrl ? $clientUrl : '',
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
			) . Html::closeElement( 'form' )
		);

		if ( $clientUrl ) {
			$this->showImportForm( $clientUrl );
		}

		$out->addHTML( Html::closeElement( 'div' ) );
	}

	private function showImportForm( $clientUrl ) {
		$this->getOutput()->addHTML(
			Html::openElement(
				'form',
				[
					'action' => $this->getPageTitle()->getLocalURL(),
					'method' => 'POST',
				]
			) . Html::element(
				'input',
				[
					'type' => 'hidden',
					'name' => 'clientUrl',
					'value' => $clientUrl,
				]
			). new ButtonInputWidget(
				[
					'classes' => [ 'mw-movtocom-url-import' ],
					'label' => ( new Message( 'fileimporter-import' ) )->plain(),
					'type' => 'submit',
					'flags' => [ 'primary', 'progressive' ],
				]
			) . Html::closeElement( 'form' )
		);
	}

}
