<?php

namespace FileImporter\Services\UploadBase;

use LogicException;
use MediaWiki\Linker\LinkTarget;
use Psr\Log\LoggerInterface;
use UploadBase;
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
	 * @param LoggerInterface $logger
	 * @param LinkTarget $targetTitle
	 * @param string $tempPath
	 */
	public function __construct(
		LoggerInterface $logger,
		LinkTarget $targetTitle,
		$tempPath
	) {
		$this->initializePathInfo(
			$targetTitle->getText(),
			$tempPath,
			null,
			false
		);
		$this->logger = $logger;
	}

	/**
	 * @return bool|int True if the title is valid, or int error code from UploadBase::getTitle if not
	 */
	public function validateTitle() {
		if ( $this->getTitle() === null ) {
			return $this->mTitleError;
		}

		return true;
	}

	/**
	 * @return bool Is the file valid?
	 */
	public function validateFile() {
		$fileVerification = $this->verifyFile();

		if ( $fileVerification !== true ) {
			$this->logger->info(
				__METHOD__ . ' checks failed', [ 'fileVerification' => $fileVerification ]
			);
			return false;
		}

		return true;
	}

	/**
	 * Should never be used but must be implemented for UploadBase
	 *
	 * @param WebRequest &$request
	 */
	public function initializeFromRequest( &$request ) {
		throw new LogicException( 'Should never be called.' );
	}

	public function getSourceType() {
		return 'file';
	}

}
