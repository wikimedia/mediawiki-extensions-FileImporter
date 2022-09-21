<?php

namespace FileImporter\Services\UploadBase;

use FileImporter\Data\TextRevision;
use Hooks;
use MediaWiki\Linker\LinkTarget;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use StatusValue;
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
	 * @return int 0 if valid, a non-zero error code from UploadBase::getTitle() if not
	 */
	public function validateTitle(): int {
		return $this->getTitle() ? UploadBase::OK : $this->mTitleError;
	}

	/**
	 * @return StatusValue
	 */
	public function validateFile() {
		$fileVerification = $this->verifyFile();

		if ( $fileVerification !== true ) {
			$this->logger->info(
				__METHOD__ . ' checks failed', [ 'fileVerification' => $fileVerification ]
			);
			return StatusValue::newFatal( ...$fileVerification );
		}

		return StatusValue::newGood();
	}

	/**
	 * @param User $user user performing the import
	 * @param TextRevision|null $textRevision optional text revision to validate the upload with
	 *
	 * @return StatusValue
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

		// @phan-suppress-next-line PhanImpossibleCondition May set by hook
		if ( $error ) {
			if ( !is_array( $error ) ) {
				$error = [ $error ];
			}
			return StatusValue::newFatal( ...$error );
		}

		return StatusValue::newGood();
	}

	/**
	 * Should never be used but must be implemented for UploadBase
	 *
	 * @param WebRequest &$request
	 * @codeCoverageIgnore
	 */
	public function initializeFromRequest( &$request ) {
		// Should never be called
	}

	/**
	 * @inheritDoc
	 * @codeCoverageIgnore
	 */
	public function getSourceType() {
		return 'file';
	}

}
