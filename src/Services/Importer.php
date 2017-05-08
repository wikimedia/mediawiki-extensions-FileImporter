<?php

namespace FileImporter\Services;

use FileImporter\Data\ImportDetails;
use FileImporter\Data\ImportPlan;
use FileImporter\Data\TextRevisions;
use FileImporter\Exceptions\ImportException;
use FileImporter\MediaWiki\FileImporterUploadBase;
use Http;
use MediaWiki\Linker\LinkTarget;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use TempFSFile;
use Title;
use User;
use WikiRevision;

class Importer implements LoggerAwareInterface {

	/**
	 * @var WikiRevisionFactory
	 */
	private $wikiRevisionFactory;

	/**
	 * @var NullRevisionCreator
	 */
	private $nullRevisionCreator;

	/**
	 * @var int
	 */
	private $maxUploadSize;

	/**
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * @param WikiRevisionFactory $wikiRevisionFactory
	 * @param NullRevisionCreator $nullRevisionCreator
	 * @param int $maxUploadSize
	 */
	public function __construct(
		WikiRevisionFactory $wikiRevisionFactory,
		NullRevisionCreator $nullRevisionCreator,
		$maxUploadSize
	) {
		$this->wikiRevisionFactory = $wikiRevisionFactory;
		$this->nullRevisionCreator = $nullRevisionCreator;
		$this->maxUploadSize = $maxUploadSize;
		$this->logger = new NullLogger();
	}

	public function setLogger( LoggerInterface $logger ) {
		$this->logger = $logger;
	}

	/**
	 * @param User $user user to use for the import
	 * @param ImportPlan $importPlan
	 *
	 * @return bool success
	 * @throws ImportException
	 */
	public function import(
		User $user,
		ImportPlan $importPlan
	) {
		$importDetails = $importPlan->getDetails();
		$title = $importPlan->getTitle();

		$this->checkTitleFileExtensionsMatch(
			$importDetails->getSourceLinkTarget(),
			$title
		);

		$wikiRevisionFiles = $this->getWikiRevisionFiles( $importDetails );

		foreach ( $wikiRevisionFiles as $wikiRevisionFile ) {
			$base = new FileImporterUploadBase( $title, $wikiRevisionFile->getFileSrc() );
			$base->setLogger( $this->logger );
			if ( !$base->performChecks() ) {
				return false;
			}
		}

		if ( !isset( $base ) ) {
			$this->logger->error( __METHOD__ . ' no $base found for import.' );
			return false;
		}

		// We can assume this will be a Title and not null due to the performChecks calls above
		$title = $base->getTitle();

		// TODO copy files directly in swift if possible?

		// TODO lookup in CentralAuth to see if users can be maintained on the import
		// This probably needs some service object to be made to keep things nice and tidy

		$this->importWikiRevisionFiles( $title, $wikiRevisionFiles );
		$this->importTextRevisions( $title, $importDetails->getTextRevisions() );

		// TODO do we need to call WikiImporter::finishImportPage??
		// TODO factor logic in WikiImporter::finishImportPage out so we can call it

		$this->createPostImportNullRevision( $importPlan, $user );

		// TODO If modifications are needed on the text we need to make 1 new revision!

		// TODO think about being able to roll back these changes? / totally remove (not just
		// delete)?

		return true;
	}

	/**
	 * Check to ensure files are not imported with differing file extensions.
	 *
	 * @todo this should probably live somewhere else
	 *
	 * @param LinkTarget $linkTargetOne
	 * @param LinkTarget $linkTargetTwo
	 */
	private function checkTitleFileExtensionsMatch(
		LinkTarget $linkTargetOne,
		LinkTarget $linkTargetTwo
	) {
		if (
			pathinfo( $linkTargetOne->getText() )['extension'] !==
			pathinfo( $linkTargetTwo->getText() )['extension']
		) {
			throw new ImportException( 'Target file extension does not match original file' );
		}
	}

	/**
	 * @param ImportDetails $importDetails
	 *
	 * @return WikiRevision[]
	 */
	private function getWikiRevisionFiles( ImportDetails $importDetails ) {
		$wikiRevisionFiles = [];

		foreach ( $importDetails->getFileRevisions()->toArray() as $fileRevision ) {
			$fileUrl = $fileRevision->getField( 'url' );
			if ( !Http::isValidURI( $fileUrl ) ) {
				// TODO exception?
				die( 'oh noes, bad uri' );
			}

			$tmpFile = TempFSFile::factory( 'fileimporter_', '', wfTempDir() );
			$tmpFile->bind( $this );

			$chunkSaver = new HttpRequestFileChunkSaver( $tmpFile->getPath(), $this->maxUploadSize );
			$chunkSaver->setLogger( $this->logger );

			// TODO proxy? $wgCopyUploadProxy ?
			// TODO timeout $wgCopyUploadTimeout ?
			$httpRequestExecutor = new HttpRequestExecutor();
			$httpRequestExecutor->setLogger( $this->logger );
			$httpRequestExecutor->execute( $fileUrl, [ $chunkSaver, 'saveFileChunk' ] );

			$wikiRevisionFiles[] = $this->wikiRevisionFactory->newFromFileRevision(
				$fileRevision,
				$tmpFile->getPath(),
				true
			);
		}

		return $wikiRevisionFiles;
	}

	/**
	 * @param Title $title
	 * @param WikiRevision[] $wikiRevisionFiles
	 */
	private function importWikiRevisionFiles( Title $title, array $wikiRevisionFiles ) {
		foreach ( $wikiRevisionFiles as $wikiRevisionFile ) {
			$wikiRevisionFile->setTitle( $title );
			$importSuccess = $wikiRevisionFile->importUpload();
			if ( !$importSuccess ) {
				// TODO exception & Log
				die( 'failed import faile :/' );
			}
		}
	}

	private function importTextRevisions( Title $title, TextRevisions $textRevisions ) {
		foreach ( $textRevisions->toArray() as $textRevision ) {
			$wikiRevision = $this->wikiRevisionFactory->newFromTextRevision( $textRevision );
			$wikiRevision->setTitle( $title );
			$importSuccess = $wikiRevision->importOldRevision();
			if ( !$importSuccess ) {
				// TODO exception & Log
				die( 'failed import text :/' );
			}
		}
	}

	private function createPostImportNullRevision(
		ImportPlan $importPlan,
		User $user
	) {
		$this->nullRevisionCreator->createForLinkTarget(
			// T164729 GAID_FOR_UPDATE needed to select for a write
			$importPlan->getTitle()->getArticleID( Title::GAID_FOR_UPDATE ),
			$user,
			'Imported from ' . $importPlan->getRequest()->getUrl(), // TODO i18n
			true
		);
	}

}
