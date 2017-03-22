<?php

namespace FileImporter;

use File;
use FileImporter\Generic\Data\ImportTransformations;
use FileImporter\Generic\Data\ImportDetails;
use FileImporter\Generic\Services\DetailRetriever;
use FileImporter\Generic\Services\DuplicateFileRevisionChecker;
use FileImporter\Generic\Services\Importer;
use FileImporter\Generic\Data\TargetUrl;
use FileImporter\Html\ImportPreviewPage;
use FileImporter\Html\InputFormPage;
use Html;
use MediaWiki\MediaWikiServices;
use Message;
use SpecialPage;

class SpecialImportFile extends SpecialPage {

	/**
	 * @var DetailRetriever
	 */
	private $detailRetreiver;

	/**
	 * @var Importer
	 */
	private $importer;

	/**
	 * @var DuplicateFileRevisionChecker
	 */
	private $duplicateFileChecker;

	public function __construct() {
		parent::__construct( 'FileImporter-SpecialPage' );

		$services = MediaWikiServices::getInstance();

		// TODO inject services!
		$this->detailRetreiver = $services->getService( 'FileImporterDispatchingDetailRetriever' );
		$this->importer = $services->getService( 'FileImporterImporter' );
		$this->duplicateFileChecker = $services->getService( 'FileImporterDuplicateFileRevisionChecker' );
	}

	public function getGroupName() {
		return 'media';
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

		if ( !$targetUrl->isParsable() ) {
			$this->showUnparsableUrlMessage( $targetUrl->getUrl() );
			$this->showUrlEntryPage();
			return;
		}

		if ( !$this->detailRetreiver->canGetImportDetails( $targetUrl ) ) {
			$this->showDisallowedUrlMessage();
			$this->showUrlEntryPage();
			return;
		}

		$importDetails = $this->detailRetreiver->getImportDetails( $targetUrl );
		$duplicateFiles = $this->duplicateFileChecker->findDuplicates(
			$importDetails->getFileRevisions()->getLatest()
		);
		if ( !empty( $duplicateFiles ) ) {
			$this->showDuplicateFilesDetectedMessage( $duplicateFiles );
			return;
		}
		if ( $wasPosted ) {
			$this->doImport( $importDetails );
			return;
		}
		$this->showImportPage( $importDetails );
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

		$result = $this->importer->import(
			$this->getUser(),
			$importDetails,
			$adjustments
		);

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

	/**
	 * @param File[] $duplicateFiles
	 */
	private function showDuplicateFilesDetectedMessage( array $duplicateFiles ) {
		$this->showWarningMessage(
			( new Message( 'fileimporter-duplicatefilesdetected' ) )->plain()
		);
		$duplicatesMessage = ( new Message( 'fileimporter-duplicatefilesdetected-prefix' ) )->plain();
		$this->getOutput()->addWikiText( '\'\'\'' . $duplicatesMessage . '\'\'\'' );
		foreach ( $duplicateFiles as $file ) {
			$this->getOutput()->addWikiText( '* [[:' . $file->getTitle() . ']]' );
		}
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
		$this->getOutput()->addHTML( ( new ImportPreviewPage( $this, $importDetails ) )->getHtml() );
	}

	private function showInputForm( TargetUrl $targetUrl = null ) {
		$this->getOutput()->addHTML( ( new InputFormPage( $this, $targetUrl ) )->getHtml() );
	}

}
