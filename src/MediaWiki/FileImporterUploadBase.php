<?php

namespace FileImporter\MediaWiki;

use LogicException;
use MediaWiki\Linker\LinkTarget;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use UploadBase;

class FileImporterUploadBase extends UploadBase implements LoggerAwareInterface{

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
	 * @return bool were the checks successful & can we upload this file?
	 */
	public function performChecks() {
		$title = $this->getTitle();
		$fileVerification = $this->verifyFile();
		$checkSuccess = $title !== null && $fileVerification === true;

		if ( !$checkSuccess ) {
			$logContext = [
				'title' => $title,
				'fileVerification' => $fileVerification,
			];
			$this->logger->info(
				__METHOD__ . ' checks failed: ' . json_encode( $logContext ), $logContext
			);
		}

		return $checkSuccess;
	}

	/**
	 * Should never be used but must be implemented for UploadBase
	 */
	public function initializeFromRequest( &$request ) {
		throw new LogicException( 'Should never be called.' );
	}

	public function getSourceType() {
		return 'file';
	}

}
