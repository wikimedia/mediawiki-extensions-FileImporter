<?php

namespace FileImporter;

use FileImporter\Generic\Importer;
use FileImporter\Generic\TargetUrl;
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

		$targetUrl = new TargetUrl( $out->getRequest()->getVal( 'clientUrl', '' ) );
		$wasPosted = $out->getRequest()->wasPosted();

		if ( !$targetUrl->getUrl() ) {
			$this->showUrlEntryPage();
			return;
		}

		// TODO inject!
		/** @var Importer $importer */
		$importer = MediaWikiServices::getInstance()
			->getService( 'FileImporterDispatchingImporter' );

		if ( !$targetUrl->isParsable() ) {
			$this->showUnparsableUrlMessage( $targetUrl->getUrl() );
			$this->showUrlEntryPage();
		} elseif ( !$importer->canImport( $targetUrl ) ) {
			$this->showDisallowedUrlMessage();
			$this->showUrlEntryPage();
		} else {
			if ( $wasPosted ) {
				$this->doImport( $importer, $targetUrl );
			} else {
				$this->showImportPage( $importer, $targetUrl );
			}
		}
	}

	private function doImport( Importer $importer, TargetUrl $targetUrl ) {
		// TODO implement importing
		$this->getOutput()->addHTML( 'TODO do the import' );
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

	private function showImportPage( Importer $importer, TargetUrl $target ) {
		$importDetails = $importer->getImportDetails( $target );
		$out = $this->getOutput();

		$this->getOutput()->addModuleStyles( 'ext.FileImporter.Special' );
		$this->showInputForm( $importDetails->getTargetUrl() );

		$out->addHTML(
			Html::rawElement(
				'p',
				[],
				( new Message( 'fileimporter-importfilefromprefix' ) )->plain() . ': ' .
				Linker::makeExternalLink(
					$importDetails->getTargetUrl()->getUrl(),
					$importDetails->getTargetUrl()->getUrl()
				)
			)
		);

		$out->addHTML( Html::element( 'p', [], $importDetails->getTitleText() ) );
		$out->addHTML(
			Linker::makeExternalImage(
				$importDetails->getImageDisplayUrl(),
				$importDetails->getTitleText()
			)
		);
	}

	/**
	 * @param TargetUrl|null $targetUrl
	 */
	private function showInputForm( $targetUrl = null ) {
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
					'value' => $targetUrl ? $targetUrl->getUrl() : '',
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

		if ( $targetUrl ) {
			$this->showImportForm( $targetUrl );
		}

		$out->addHTML( Html::closeElement( 'div' ) );
	}

	private function showImportForm( TargetUrl $targetUrl ) {
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
					'value' => $targetUrl->getUrl(),
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
