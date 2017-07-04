<?php

namespace FileImporter\Services;

use LogicException;
use MediaWiki\Linker\LinkTarget;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use UploadBase;
use WebRequest;

class FileImporterUploadBase extends UploadBase implements LoggerAwareInterface {

	/**
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * @param LinkTarget $targetTitle
	 * @param string $tempPath
	 */
	public function __construct( LinkTarget $targetTitle, $tempPath ) {
		$this->initializePathInfo(
			$targetTitle->getText(),
			$tempPath,
			null,
			false
		);
		$this->logger = new NullLogger();
	}

	public function setLogger( LoggerInterface $logger ) {
		$this->logger = $logger;
	}

	/**
	 * @return bool|int check success or integer error code
	 */
	public function performTitleChecks() {
		if ( $this->getTitle() === null ) {
			return $this->mTitleError;
		}

		return true;
	}

	/**
	 * @return bool were the checks successful & can we upload this file?
	 */
	public function performFileChecks() {
		$fileVerification = $this->verifyFile();

		if ( $fileVerification !== true ) {
			// TODO throw a more informative exception?
			$logContext = [ 'fileVerification' => $fileVerification, ];
			$this->logger->info(
				__METHOD__ . ' checks failed: ' . json_encode( $logContext ), $logContext
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
