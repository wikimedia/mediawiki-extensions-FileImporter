<?php

namespace FileImporter\Services\Http;

use FileImporter\Exceptions\ImportException;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * This should not be used directly.
 * Please see HttpRequestExecutor::executeAndSave
 *
 * TODO this could end up in core? and used by UploadFromUrl?
 *
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class FileChunkSaver implements LoggerAwareInterface {

	private const ERROR_CHUNK_OPEN = 'chunkNotOpened';
	private const ERROR_CHUNK_SAVE = 'chunkNotSaved';

	/**
	 * @var string
	 */
	private $filePath;

	/**
	 * @var int
	 */
	private $maxBytes;

	/**
	 * @var null|resource|bool
	 */
	private $handle = null;

	/**
	 * @var int
	 */
	private $fileSize = 0;

	/**
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * @param string $filePath
	 * @param int $maxBytes
	 */
	public function __construct( $filePath, $maxBytes ) {
		$this->filePath = $filePath;
		$this->maxBytes = $maxBytes;
		$this->logger = new NullLogger();
	}

	/**
	 * @param LoggerInterface $logger
	 * @codeCoverageIgnore
	 */
	public function setLogger( LoggerInterface $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Get the file resource. Open the file if it was not already open.
	 * @return resource|bool
	 */
	private function getHandle() {
		if ( $this->handle === null ) {
			try {
				$this->handle = fopen( $this->filePath, 'wb' );
			} catch ( \Throwable $e ) {
				$this->logger->debug( 'Failed to get file handle: "' . $e->getMessage() . '"' );
			}

			if ( !$this->handle ) {
				$this->logger->debug( 'File creation failed "' . $this->filePath . '"' );
				throw new ImportException(
					'Failed to open file "' . $this->filePath . '"', self::ERROR_CHUNK_OPEN );
			} else {
				$this->logger->debug( 'File created "' . $this->filePath . '"' );
			}
		}

		return $this->handle;
	}

	/**
	 * Callback: save a chunk of the result of an HTTP request to the file.
	 * Intended for use with HttpRequestFactory::request
	 *
	 * @param int $curlResource Required by the cURL library, see CURLOPT_WRITEFUNCTION
	 * @param string $buffer
	 *
	 * @return int Number of bytes handled
	 * @throws ImportException
	 */
	public function saveFileChunk( $curlResource, $buffer ) {
		$handle = $this->getHandle();
		$this->logger->debug( 'Received chunk of ' . strlen( $buffer ) . ' bytes' );
		$nbytes = fwrite( $handle, $buffer );

		$this->throwExceptionIfOnShortWrite( $nbytes, $buffer );
		$this->fileSize += $nbytes;
		$this->throwExceptionIfMaxBytesExceeded();

		return $nbytes;
	}

	/**
	 * @param int $nbytes
	 * @param string $buffer
	 */
	private function throwExceptionIfOnShortWrite( $nbytes, $buffer ): void {
		if ( $nbytes != strlen( $buffer ) ) {
			$this->closeHandleLogAndThrowException(
				'Short write ' . $nbytes . '/' . strlen( $buffer ) .
				' bytes, aborting with ' . $this->fileSize . ' uploaded so far'
			);
		}
	}

	private function throwExceptionIfMaxBytesExceeded() {
		if ( $this->fileSize > $this->maxBytes ) {
			$this->closeHandleLogAndThrowException(
				'File downloaded ' . $this->fileSize . ' bytes, ' .
				'exceeds maximum ' . $this->maxBytes . ' bytes.'
			);
		}
	}

	/**
	 * @param string $message
	 * @return never
	 */
	private function closeHandleLogAndThrowException( $message ) {
		$this->closeHandle();
		$this->logger->debug( $message );
		throw new ImportException( $message, self::ERROR_CHUNK_SAVE );
	}

	private function closeHandle() {
		fclose( $this->handle );
		$this->handle = false;
	}

}
