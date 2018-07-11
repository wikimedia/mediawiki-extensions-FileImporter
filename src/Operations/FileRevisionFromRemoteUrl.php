<?php

namespace FileImporter\Operations;

use FileImporter\Data\FileRevision;
use FileImporter\Data\TextRevision;
use FileImporter\Exceptions\ValidationException;
use FileImporter\Interfaces\ImportOperation;
use FileImporter\Services\Http\HttpRequestExecutor;
use FileImporter\Services\UploadBase\UploadBaseFactory;
use FileImporter\Services\UploadBase\ValidatingUploadBase;
use FileImporter\Services\WikiRevisionFactory;
use Http;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use TempFSFile;
use Title;
use UploadRevisionImporter;
use User;
use WikiRevision;

/**
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class FileRevisionFromRemoteUrl implements ImportOperation {

	/**
	 * @var Title
	 */
	private $plannedTitle;

	/**
	 * @var User user performing the import
	 */
	private $user;

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

	/**
	 * @var TextRevision|null
	 */
	private $textRevision;

	/**
	 * @var ValidatingUploadBase|null
	 */
	private $uploadBase = null;

	/**
	 * @var UploadRevisionImporter
	 */
	private $importer;

	/**
	 * @param Title $plannedTitle
	 * @param User $user
	 * @param FileRevision $fileRevision
	 * @param TextRevision|null $textRevision
	 * @param HttpRequestExecutor $httpRequestExecutor
	 * @param WikiRevisionFactory $wikiRevisionFactory
	 * @param UploadBaseFactory $uploadBaseFactory
	 * @param UploadRevisionImporter $importer
	 * @param LoggerInterface|null $logger
	 */
	public function __construct(
		Title $plannedTitle,
		User $user,
		FileRevision $fileRevision,
		TextRevision $textRevision = null,
		HttpRequestExecutor $httpRequestExecutor,
		WikiRevisionFactory $wikiRevisionFactory,
		UploadBaseFactory $uploadBaseFactory,
		UploadRevisionImporter $importer,
		LoggerInterface $logger = null
	) {
		$this->plannedTitle = $plannedTitle;
		$this->user = $user;
		$this->fileRevision = $fileRevision;
		$this->textRevision = $textRevision;
		$this->httpRequestExecutor = $httpRequestExecutor;
		$this->wikiRevisionFactory = $wikiRevisionFactory;
		$this->uploadBaseFactory = $uploadBaseFactory;
		$this->importer = $importer;
		$this->logger = $logger ?: new NullLogger();
	}

	/**
	 * Method to prepare an operation. This will not commit anything to any persistent storage.
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
			$tmpFile->getPath()
		);

		$this->wikiRevision->setTitle( $this->plannedTitle );

		$this->uploadBase = $this->uploadBaseFactory->newValidatingUploadBase(
			$this->plannedTitle,
			$this->wikiRevision->getFileSrc()
		);

		return true;
	}

	/**
	 * Method to validate prepared data that should be committed.
	 * @return bool success
	 * @throws ValidationException
	 */
	public function validate() {
		$result = $this->uploadBase->validateTitle() === true &&
			$this->uploadBase->validateFile() === true;

		if ( !$result ) {
			$this->logger->error(
				__METHOD__ . ' failed to validate.',
				[ 'fileRevision-getFields' => $this->fileRevision->getFields() ]
			);
		}

		$uploadValidationStatus = $this->uploadBase->validateUpload(
			$this->user,
			$this->textRevision
		);

		if ( !$uploadValidationStatus->isGood() ) {
			throw new ValidationException( $uploadValidationStatus->getHTML() );
		}

		return $result;
	}

	/**
	 * Commit this operation to persistent storage.
	 * @return bool success
	 */
	public function commit() {
		$result = $this->importer->import( $this->wikiRevision );

		if ( !$result->isGood() ) {
			$this->logger->error(
				__METHOD__ . ' failed to commit.',
				[ 'fileRevision-getFields' => $this->fileRevision->getFields() ]
			);
		}

		return $result->isGood();
	}

	/**
	 * @return WikiRevision|null
	 */
	public function getWikiRevison() {
		return $this->wikiRevision;
	}

}
