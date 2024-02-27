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

	/** @var LoggerInterface */
	private $logger;

	public function __construct( LoggerInterface $logger ) {
		$this->logger = $logger;
	}

	/**
	 * @param LinkTarget $targetTitle
	 * @param string $tempPath
	 */
	public function newValidatingUploadBase( LinkTarget $targetTitle, $tempPath ): ValidatingUploadBase {
		return new ValidatingUploadBase( $targetTitle, $tempPath, $this->logger );
	}

}
