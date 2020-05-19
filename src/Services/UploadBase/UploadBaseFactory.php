<?php

namespace FileImporter\Services\UploadBase;

use MediaWiki\Linker\LinkTarget;
use Psr\Log\LoggerInterface;

/**
 * @license GPL-2.0-or-later
 * @author Addshore
 *
 * @codeCoverageIgnore
 */
class UploadBaseFactory {

	/**
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * @param LoggerInterface $logger
	 */
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
		return new ValidatingUploadBase( $targetTitle, $tempPath, $this->logger );
	}

}
