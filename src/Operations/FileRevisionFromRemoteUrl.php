<?php

namespace FileImporter\Operations;

use FileImporter\Data\FileRevision;
use FileImporter\Interfaces\ImportOperation;
use FileImporter\Services\Http\HttpRequestExecutor;
use FileImporter\Services\UploadBase\UploadBaseFactory;
use FileImporter\Services\WikiRevisionFactory;
use Http;
use Psr\Log\LoggerInterface;
use TempFSFile;
use Title;
use WikiRevision;

class FileRevisionFromRemoteUrl implements ImportOperation {

	/**
	 * @var Title
	 */
	private $plannedTitle;

	/**
	 * @var FileRevision
	 */
	private $fileRevision;

	/**
	 * @var HttpRequestExecutor
	 */
	private $httpRequestExecutor;

	/**
	 * @var WikiRevisionFactory
	 */
	private $wikiRevisionFactory;

	/**
	 * @var UploadBaseFactory
	 */
	private $uploadBaseFactory;

	/**
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * @var WikiRevision|null
	 */
	private $wikiRevision;

	public function __construct(
		Title $plannedTitle,
		FileRevision $fileRevision,
		HttpRequestExecutor $httpRequestExecutor,
		WikiRevisionFactory $wikiRevisionFactory,
		UploadBaseFactory $uploadBaseFactory,
		LoggerInterface $logger
	) {
		$this->plannedTitle = $plannedTitle;
		$this->fileRevision = $fileRevision;
		$this->httpRequestExecutor = $httpRequestExecutor;
		$this->wikiRevisionFactory = $wikiRevisionFactory;
		$this->uploadBaseFactory = $uploadBaseFactory;
		$this->logger = $logger;
	}

	/**
	 * Method to prepare an operation. This will not commit anything to any persistent storage.
	 * For example, this could make API calls and validate data.
	 * @return bool success
	 */
	public function prepare() {
		$fileUrl = $this->fileRevision->getField( 'url' );
		if ( !Http::isValidURI( $fileUrl ) ) {
			// invalid URL detected
			return false;
		}

		$tmpFile = TempFSFile::factory( 'fileimporter_', '', wfTempDir() );
		$tmpFile->bind( $this );

		$this->httpRequestExecutor->executeAndSave( $fileUrl, $tmpFile->getPath() );

		$this->wikiRevision = $this->wikiRevisionFactory->newFromFileRevision(
			$this->fileRevision,
			$tmpFile->getPath(),
			true
		);

		$this->wikiRevision->setTitle( $this->plannedTitle );

		$base = $this->uploadBaseFactory->newValidatingUploadBase(
			$this->plannedTitle,
			$this->wikiRevision->getFileSrc()
		);

		$result = $base->validateTitle() === true && $base->validateFile() === true;

		if ( !$result ) {
			$this->logger->error(
				__METHOD__ . ' failed to prepare.',
				$this->fileRevision->getFields()
			);
		}

		return $result;
	}

	/**
	 * Commit this operation to persistent storage.
	 * @return bool success
	 */
	public function commit() {
		$result = $this->wikiRevision->importUpload();

		if ( !$result ) {
			$this->logger->error(
				__METHOD__ . ' failed to commit.',
				$this->fileRevision->getFields()
			);
		}

		return $result;
	}

}
