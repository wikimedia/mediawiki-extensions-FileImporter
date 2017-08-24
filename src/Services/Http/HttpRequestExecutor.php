<?php

namespace FileImporter\Services\Http;

use FileImporter\Exceptions\HttpRequestException;
use MWException;
use MWHttpRequest;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class HttpRequestExecutor implements LoggerAwareInterface {

	/**
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * @var callable
	 */
	private $requestFactoryCallable;

	/**
	 * @var int|bool
	 */
	private $timeout;

	/**
	 * @var int|null
	 */
	private $maxFileSize;

	/**
	 * @param int|bool $timeout of http requests in seconds, false for default
	 * @param int|null $maxFileSize in bytes
	 */
	public function __construct( $timeout = false, $maxFileSize = null ) {
		$this->requestFactoryCallable = [ MWHttpRequest::class, 'factory' ];
		$this->logger = new NullLogger();
		$this->maxFileSize = $maxFileSize;
		$this->timeout = $timeout;
	}

	public function setLogger( LoggerInterface $logger ) {
		$this->logger = $logger;
	}

	public function overrideRequestFactory( callable $callable ) {
		if ( !defined( 'MW_PHPUNIT_TEST' ) ) {
			throw new MWException(
				'Cannot override MWHttpRequest::factory callback in operation.'
			);
		}
		$this->requestFactoryCallable = $callable;
	}

	/**
	 * @param string $url
	 *
	 * @throws HttpRequestException
	 * @return MWHttpRequest
	 */
	public function execute( $url ) {
		return $this->executeWithCallback( $url );
	}

	/**
	 * @param string $url
	 * @param string $filePath
	 *
	 * @throws HttpRequestException
	 * @return MWHttpRequest
	 */
	public function executeAndSave( $url, $filePath ) {
		$chunkSaver = new FileChunkSaver( $filePath, $this->maxFileSize );
		$chunkSaver->setLogger( $this->logger );
		return $this->executeWithCallback( $url, [ $chunkSaver, 'saveFileChunk' ] );
	}

	/**
	 * TODO proxy? $wgCopyUploadProxy ?
	 *
	 * @param string $url
	 * @param callable|null $callback
	 *
	 * @throws HttpRequestException
	 * @return MWHttpRequest
	 */
	public function executeWithCallback( $url, $callback = null ) {
		$options = [
			'logger' => $this->logger,
			'followRedirects' => true,
		];
		if ( $this->timeout !== false ) {
			$options['timeout'] = $this->timeout;
		}

		/** @var MWHttpRequest $request */
		$request = call_user_func(
			$this->requestFactoryCallable,
			$url,
			$options,
			__METHOD__
		);

		$request->setCallback( $callback );

		$status = $request->execute();
		if ( !$status->isOK() ) {
			throw new HttpRequestException( $status, $request );
		}

		return $request;
	}

}
