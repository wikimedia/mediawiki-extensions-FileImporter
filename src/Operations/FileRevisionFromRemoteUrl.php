<?php

namespace FileImporter\Operations;

use FileImporter\Data\FileRevision;
use FileImporter\Data\TextRevision;
use FileImporter\Exceptions\HttpRequestException;
use FileImporter\Exceptions\ImportException;
use FileImporter\Exceptions\LocalizedImportException;
use FileImporter\Interfaces\ImportOperation;
use FileImporter\Services\Http\HttpRequestExecutor;
use FileImporter\Services\UploadBase\UploadBaseFactory;
use FileImporter\Services\UploadBase\ValidatingUploadBase;
use FileImporter\Services\WikiRevisionFactory;
use ManualLogEntry;
use MediaWiki\FileBackend\FSFile\TempFSFileFactory;
use MediaWiki\Permissions\RestrictionStore;
use MediaWiki\User\UserIdentityLookup;
use MWHttpRequest;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Status;
use StatusValue;
use Title;
use UnexpectedValueException;
use UploadBase;
use UploadRevisionImporter;
use User;
use WikiRevision;

/**
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class FileRevisionFromRemoteUrl implements ImportOperation {

	/** @var Title */
	private $plannedTitle;
	/** @var User */
	private $user;
	/** @var FileRevision */
	private $fileRevision;
	/** @var TextRevision|null */
	private $textRevision;
	/** @var UserIdentityLookup */
	private $userLookup;
	/** @var HttpRequestExecutor */
	private $httpRequestExecutor;
	/** @var WikiRevisionFactory */
	private $wikiRevisionFactory;
	/** @var UploadBaseFactory */
	private $uploadBaseFactory;
	/** @var UploadRevisionImporter */
	private $importer;
	/** @var RestrictionStore */
	private $restrictionStore;
	/** @var LoggerInterface */
	private $logger;

	/** @var WikiRevision|null */
	private $wikiRevision = null;
	/** @var ValidatingUploadBase|null */
	private $uploadBase = null;

	/**
	 * @param Title $plannedTitle
	 * @param User $user user performing the import
	 * @param FileRevision $fileRevision
	 * @param TextRevision|null $textRevision
	 * @param UserIdentityLookup $userLookup
	 * @param HttpRequestExecutor $httpRequestExecutor
	 * @param WikiRevisionFactory $wikiRevisionFactory
	 * @param UploadBaseFactory $uploadBaseFactory
	 * @param UploadRevisionImporter $importer
	 * @param RestrictionStore $restrictionStore
	 * @param LoggerInterface|null $logger
	 */
	public function __construct(
		Title $plannedTitle,
		User $user,
		FileRevision $fileRevision,
		?TextRevision $textRevision,
		UserIdentityLookup $userLookup,
		HttpRequestExecutor $httpRequestExecutor,
		WikiRevisionFactory $wikiRevisionFactory,
		UploadBaseFactory $uploadBaseFactory,
		UploadRevisionImporter $importer,
		RestrictionStore $restrictionStore,
		LoggerInterface $logger = null
	) {
		$this->plannedTitle = $plannedTitle;
		$this->user = $user;
		$this->fileRevision = $fileRevision;
		$this->textRevision = $textRevision;
		$this->userLookup = $userLookup;
		$this->httpRequestExecutor = $httpRequestExecutor;
		$this->wikiRevisionFactory = $wikiRevisionFactory;
		$this->uploadBaseFactory = $uploadBaseFactory;
		$this->importer = $importer;
		$this->restrictionStore = $restrictionStore;
		$this->logger = $logger ?: new NullLogger();
	}

	/**
	 * Method to prepare an operation. This will not commit anything to any persistent storage.
	 * @return StatusValue isOK on success
	 */
	public function prepare(): StatusValue {
		$fileUrl = $this->fileRevision->getField( 'url' );
		if ( !MWHttpRequest::isValidURI( $fileUrl ) ) {
			// invalid URL detected
			return StatusValue::newFatal( 'fileimporter-cantparseurl' );
		}

		$tmpFile = ( new TempFSFileFactory( wfTempDir() ) )->newTempFSFile( 'fileimporter_' );
		$tmpFile->bind( $this );

		try {
			$this->httpRequestExecutor->executeAndSave( $fileUrl, $tmpFile->getPath() );
		} catch ( HttpRequestException $ex ) {
			if ( $ex->getCode() === 404 ) {
				throw new LocalizedImportException( 'fileimporter-filemissinginrevision', $ex );
			}
			throw $ex;
		}

		$this->wikiRevision = $this->wikiRevisionFactory->newFromFileRevision(
			$this->fileRevision,
			$tmpFile->getPath()
		);

		$this->wikiRevision->setTitle( $this->plannedTitle );

		$this->uploadBase = $this->uploadBaseFactory->newValidatingUploadBase(
			$this->plannedTitle,
			$this->wikiRevision->getFileSrc()
		);

		return StatusValue::newGood();
	}

	/**
	 * Method to validate prepared data that should be committed.
	 *
	 * @return StatusValue isOK on success
	 * @throws ImportException when critical validations fail
	 */
	public function validate(): StatusValue {
		$errorCode = $this->uploadBase->validateTitle();
		if ( $errorCode !== UploadBase::OK ) {
			$this->logger->error(
				__METHOD__ . " failed to validate title, error code {$errorCode}",
				[ 'fileRevision-getFields' => $this->fileRevision->getFields() ]
			);
			return StatusValue::newFatal( 'fileimporter-filenameerror-illegal' );
		}

		// Even administrators should not (accidentially) move a file to a protected file name
		if ( $this->restrictionStore->isProtected( $this->plannedTitle ) ) {
			return StatusValue::newFatal( 'fileimporter-filenameerror-protected' );
		}

		$fileValidationStatus = $this->uploadBase->validateFile();
		if ( !$fileValidationStatus->isOK() ) {
			$message = Status::wrap( $fileValidationStatus )->getMessage();
			return StatusValue::newFatal( 'fileimporter-cantimportfileinvalid', $message );
		}

		return $this->uploadBase->validateUpload(
			$this->user,
			$this->textRevision
		);
	}

	/**
	 * Commit this operation to persistent storage.
	 * @return StatusValue isOK on success
	 */
	public function commit(): StatusValue {
		$status = $this->importer->import( $this->wikiRevision );

		if ( !$status->isGood() ) {
			$this->logger->error(
				__METHOD__ . ' failed to commit.',
				[ 'fileRevision-getFields' => $this->fileRevision->getFields() ]
			);
		}

		/**
		 * Core only creates log entries for the latest revision. This results in a complete upload
		 * log only when the revisions are uploaded in chronological order, and all using the same
		 * file name.
		 *
		 * Here we are not only working in reverse chronological order, but also with archive file
		 * names that are all different. Core can't know if it needs to create historical log
		 * entries for these.
		 *
		 * According to {@see \LocalFile::publishTo} the {@see \StatusValue::$value} contains the
		 * archive file name.
		 */
		if ( $status->value !== '' ) {
			$this->createUploadLog();
		}

		return $status;
	}

	/**
	 * @see \LocalFile::recordUpload2
	 */
	private function createUploadLog() {
		$username = $this->wikiRevision->getUser();
		$performer = $this->userLookup->getUserIdentityByName( $username );
		if ( !$performer ) {
			throw new UnexpectedValueException( "Unexpected non-normalized username \"$username\"" );
		}

		$logEntry = new ManualLogEntry( 'upload', 'upload' );
		$logEntry->setTimestamp( $this->wikiRevision->getTimestamp() );
		$logEntry->setPerformer( $performer );
		$logEntry->setComment( $this->wikiRevision->getComment() );
		$logEntry->setAssociatedRevId( $this->wikiRevision->getID() );
		$logEntry->setTarget( $this->wikiRevision->getTitle() );
		$logEntry->setParameters(
			[
				'img_sha1' => $this->wikiRevision->getSha1(),
				'img_timestamp' => $this->wikiRevision->getTimestamp()
			]
		);

		$logId = $logEntry->insert();
		$logEntry->publish( $logId );
	}

	/**
	 * @return WikiRevision|null
	 */
	public function getWikiRevision() {
		return $this->wikiRevision;
	}

}
