<?php

namespace FileImporter\Services\UploadBase;

use FileImporter\Data\TextRevision;
use Hooks;
use LogicException;
use MediaWiki\Linker\LinkTarget;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Status;
use UploadBase;
use User;
use WebRequest;

/**
 * This class extends the MediaWiki UploadBase class in order to perform validation
 * that is normally carried out as part of the upload process.
 * Ideally MediaWiki core would be refactored so that this could more easily be accessed.
 *
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class ValidatingUploadBase extends UploadBase {

	/**
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * @param LinkTarget $targetTitle
	 * @param string $tempPath
	 * @param LoggerInterface|null $logger
	 */
	public function __construct(
		LinkTarget $targetTitle,
		$tempPath,
		LoggerInterface $logger = null
	) {
		$this->initializePathInfo(
			$targetTitle->getText(),
			$tempPath,
			null,
			false
		);
		$this->logger = $logger ?: new NullLogger();
	}

	/**
	 * @return bool|int True if the title is valid, or int error code from UploadBase::getTitle if not
	 */
	public function validateTitle() {
		if ( !$this->getTitle() ) {
			return $this->mTitleError;
		}

		return true;
	}

	/**
	 * @return Status
	 */
	public function validateFile() {
		$fileVerification = $this->verifyFile();

		if ( $fileVerification !== true ) {
			$this->logger->info(
				__METHOD__ . ' checks failed', [ 'fileVerification' => $fileVerification ]
			);
			return call_user_func_array( [ Status::class, 'newFatal' ], $fileVerification );
		}

		return Status::newGood();
	}

	/**
	 * @param User $user user performing the import
	 * @param TextRevision|null $textRevision optional text revision to validate the upload with
	 *
	 * @return Status
	 */
	public function validateUpload( User $user, TextRevision $textRevision = null ) {
		$error = null;

		if ( !$textRevision ) {
			Hooks::run( 'UploadStashFile', [
				$this,
				$user,
				$this->mFileProps,
				&$error
			] );
		} else {
			Hooks::run( 'UploadVerifyUpload', [
				$this,
				$user,
				$this->mFileProps,
				$textRevision->getField( 'comment' ),
				$textRevision->getField( '*' ),
				&$error
			] );
		}

		if ( $error ) {
			if ( !is_array( $error ) ) {
				$error = [ $error ];
			}
			return call_user_func_array( [ Status::class, 'newFatal' ], $error );
		}

		return Status::newGood();
	}

	/**
	 * Should never be used but must be implemented for UploadBase
	 *
	 * @param WebRequest &$request
	 * @codeCoverageIgnore
	 */
	public function initializeFromRequest( &$request ) {
		throw new LogicException( 'Should never be called.' );
	}

	/**
	 * @inheritDoc
	 * @codeCoverageIgnore
	 */
	public function getSourceType() {
		return 'file';
	}

}
