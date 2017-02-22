<?php

namespace FileImporter;

use FileImporter\Generic\ImportTransformations;
use FileImporter\Generic\ImportDetails;
use FileImporter\Generic\DetailRetriever;
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

		$this->getOutput()->addModuleStyles( 'ext.FileImporter.Special' );

		if ( !$targetUrl->getUrl() ) {
			$this->showUrlEntryPage();
			return;
		}

		// TODO inject!
		/** @var DetailRetriever $detailRetriever */
		$detailRetriever = MediaWikiServices::getInstance()
			->getService( 'FileImporterDispatchingDetailRetriever' );

		if ( !$targetUrl->isParsable() ) {
			$this->showUnparsableUrlMessage( $targetUrl->getUrl() );
			$this->showUrlEntryPage();
		} elseif ( !$detailRetriever->canGetImportDetails( $targetUrl ) ) {
			$this->showDisallowedUrlMessage();
			$this->showUrlEntryPage();
		} else {
			$importDetails = $detailRetriever->getImportDetails( $targetUrl );
			if ( $wasPosted ) {
				$this->doImport( $importDetails );
			} else {
				$this->showImportPage( $importDetails );
			}
		}
	}

	private function doImport( ImportDetails $importDetails ) {
		$out = $this->getOutput();

		$importDetailsHash = $out->getRequest()->getVal( 'importDetailsHash', '' );
		$token = $out->getRequest()->getVal( 'token', '' );

		if ( $this->getUser()->getEditToken() !== $token ) {
			$this->showWarningMessage( 'Incorrect token submitted for import' ); // TODO i18n
		}

		if ( $importDetails->getHash() !== $importDetailsHash ) {
			// TODO i18n
			$this->showWarningMessage( 'Incorrect import details hash submitted for import' );
		}

		$adjustments = new ImportTransformations(); // TODO populate adjustments based on import form

		// TODO inject?!
		$importer = new Importer();
		$result = $importer->import( $importDetails, $adjustments );

		if ( $result ) {
			// TODO show completion page showing old and new files & other possible actions
			$this->getOutput()->addHTML( 'Import was a success!' ); // TODO i18n
		} else {
			$this->showWarningMessage( 'Import failed' ); // TODO i18n
		}

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

	private function showImportPage( ImportDetails $importDetails ) {
		$out = $this->getOutput();
		$this->showInputForm( $importDetails->getTargetUrl() );
		$this->showImportForm( $importDetails );

		// TODO output a version of the text to be modified by the user!
		// This text should already have the configurable modifications applied & probably a list
		// of what has been changed / a diff?

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

		$out->addHTML(
			Html::element(
				'p',
				[],
				( new Message( 'fileimporter-titleprefix' ) )->plain() . ': ' .
				$importDetails->getTitleText()
			)
		);
		$out->addHTML(
			Html::element(
				'p',
				[],
				( new Message( 'fileimporter-textrevisionsprefix' ) )->plain() . ': ' .
					count( $importDetails->getTextRevisions() )
			)
		);
		$out->addHTML(
			Html::element(
				'p',
				[],
				( new Message( 'fileimporter-filerevisionsprefix' ) )->plain() . ': ' .
					count( $importDetails->getFileRevisions() )
			)
		);
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

		$out->addHTML( Html::closeElement( 'div' ) );
	}

	private function showImportForm( ImportDetails $importDetails ) {
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
					'value' => $importDetails->getTargetUrl()->getUrl(),
				]
			) . Html::element(
				'input',
				[
					'type' => 'hidden',
					'name' => 'importDetailsHash',
					'value' => $importDetails->getHash(),
				]
			) . Html::element(
				'input',
				[
					'type' => 'hidden',
					'name' => 'token',
					'value' => $this->getUser()->getEditToken()
				]
			) . new ButtonInputWidget(
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
