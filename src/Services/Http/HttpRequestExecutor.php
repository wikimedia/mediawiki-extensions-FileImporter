<?php

namespace FileImporter\Services\Http;

use FileImporter\Exceptions\HttpRequestException;
use MWException;
use MWHttpRequest;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * @license GPL-2.0-or-later
 * @author Addshore
 */
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
	 * @var array
	 */
	private $httpOptions;

	/**
	 * @var int
	 */
	private $maxFileSize;

	/**
	 * @param array $httpOptions in the following format:
	 * [
	 *     'proxy' => string|false,
	 *     'timeout' => int|false Timeout of HTTP requests in seconds, false for default,
	 * ]
	 * @param int $maxFileSize in bytes
	 */
	public function __construct( array $httpOptions, $maxFileSize ) {
		$this->requestFactoryCallable = [ MWHttpRequest::class, 'factory' ];
		$this->logger = new NullLogger();
		$this->maxFileSize = $maxFileSize;
		$this->httpOptions = $httpOptions;
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
		if ( !empty( $this->httpOptions['proxy'] ) ) {
			$options['proxy'] = $this->httpOptions['proxy'];
		}
		if ( isset( $this->httpOptions['timeout'] ) && $this->httpOptions['timeout'] !== false ) {
			$options['timeout'] = $this->httpOptions['timeout'];
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
