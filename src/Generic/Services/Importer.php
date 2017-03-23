<?php

namespace FileImporter\Generic\Services;

use FileImporter\Generic\Data\ImportDetails;
use FileImporter\Generic\Data\ImportTransformations;
use FileImporter\Generic\Data\TextRevisions;
use FileImporter\Generic\Exceptions\ImportException;
use Http;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use TempFSFile;
use Title;
use User;

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
	 * @param Title $title target title of the import
	 * @param ImportDetails $importDetails
	 * @param ImportTransformations $importTransformations transformations to be made to the details
	 *
	 * @return bool success
	 * @throws ImportException
	 */
	public function import(
		User $user,
		Title $title,
		ImportDetails $importDetails,
		ImportTransformations $importTransformations
	) {
		$this->checkTitleFileExtensionsMatch( $title, $importDetails->getPrefixedTitleText() );

		// TODO copy files directly in swift if possible?

		// TODO lookup in CentralAuth to see if users can be maintained on the import
		// This probably needs some service object to be made to keep things nice and tidy

		$wikiRevisionFiles = $this->getWikiRevisionFiles( $importDetails );
		$this->importWikiRevisionFiles( $title, $wikiRevisionFiles );
		$this->importTextRevisions( $title, $importDetails->getTextRevisions() );

		// TODO do we need to call WikiImporter::finishImportPage??
		// TODO factor logic in WikiImporter::finishImportPage out so we can call it

		$this->createPostImportNullRevision( $importDetails, $user, $title );

		// TODO If modifications are needed on the text we need to make 1 new revision!
		// @see RevisionModifier ?

		// TODO think about being able to roll back these changes? / totally remove (not just
		// delete)?

		return true;
	}

	/**
	 * Check to ensure files are not imported with differing file extensions.
	 *
	 * @param Title $title
	 * @param string $prefixedText File:Foo.png
	 */
	private function checkTitleFileExtensionsMatch( Title $title, $prefixedText ) {
		if (
			pathinfo( $title->getPrefixedText() )['extension'] !==
			pathinfo( $prefixedText )['extension']
		) {
			throw new ImportException( 'Target file extension does not match original file' );
		}
	}

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
		ImportDetails $importDetails,
		User $user,
		Title $title
	) {
		$this->nullRevisionCreator->createForLinkTarget(
			$title->getArticleID(),
			$user,
			'Imported from ' . $importDetails->getTargetUrl()->getUrl(), // TODO i18n
			true
		);
	}

}
