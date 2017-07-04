<?php

namespace FileImporter\Services\UploadBase;

use MediaWiki\Linker\LinkTarget;
use Psr\Log\LoggerInterface;

class UploadBaseFactory {

	/**
	 * @var LoggerInterface
	 */
	private $logger;

	public function __construct( LoggerInterface $logger ) {
		$this->logger = $logger;
	}

	/**
	 * @param LinkTarget $targetTitle
	 * @param string $tempPath
	 *
	 * @return ValidatingUploadBase
	 */
	public function newValidatingUploadBase( LinkTarget $targetTitle, $tempPath ) {
		$base = new ValidatingUploadBase( $this->logger, $targetTitle, $tempPath );
		return $base;
	}

}
