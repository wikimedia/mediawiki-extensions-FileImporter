<?php

namespace FileImporter\Services\Http;

use FileImporter\Exceptions\HttpRequestException;
use MediaWiki\Http\HttpRequestFactory;
use MWHttpRequest;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class HttpRequestExecutor implements LoggerAwareInterface {

	private HttpRequestFactory $httpRequestFactory;
	private LoggerInterface $logger;
	private int $maxFileSize;
	private array $httpOptions;

	/**
	 * @param HttpRequestFactory $httpRequestFactory
	 * @param array $httpOptions in the following format:
	 * [
	 *     'originalRequest' => WebRequest|string[] When in array form, it's expected to have the
	 *         keys 'ip' and 'userAgent', {@see MWHttpRequest::setOriginalRequest},
	 *     'proxy' => string|false,
	 *     'timeout' => int|false Timeout of HTTP requests in seconds, false for default,
	 * ]
	 * @param int $maxFileSize in bytes
	 */
	public function __construct(
		HttpRequestFactory $httpRequestFactory,
		array $httpOptions,
		int $maxFileSize
	) {
		$this->httpRequestFactory = $httpRequestFactory;
		$this->logger = new NullLogger();
		$this->maxFileSize = $maxFileSize;
		$this->httpOptions = $httpOptions;
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function setLogger( LoggerInterface $logger ): void {
		$this->logger = $logger;
	}

	/**
	 * @throws HttpRequestException
	 */
	public function execute( string $url, array $parameters = [] ): MWHttpRequest {
		return $this->executeHttpRequest( wfAppendQuery( $url, $parameters ) );
	}

	public function executePost( string $url, array $postData ): MWHttpRequest {
		return $this->executeHttpRequest( $url, null, $postData );
	}

	/**
	 * @throws HttpRequestException
	 */
	public function executeAndSave( string $url, string $filePath ): MWHttpRequest {
		$chunkSaver = new FileChunkSaver( $filePath, $this->maxFileSize );
		$chunkSaver->setLogger( $this->logger );
		return $this->executeHttpRequest( $url, [ $chunkSaver, 'saveFileChunk' ] );
	}

	/**
	 * @throws HttpRequestException
	 */
	private function executeHttpRequest(
		string $url,
		?callable $callback = null,
		?array $postData = null
	): MWHttpRequest {
		$options = [
			'logger' => $this->logger,
			'followRedirects' => true,
		];
		if ( isset( $this->httpOptions['originalRequest'] ) ) {
			$options['originalRequest'] = $this->httpOptions['originalRequest'];
		}
		if ( !empty( $this->httpOptions['proxy'] ) ) {
			$options['proxy'] = $this->httpOptions['proxy'];
		}
		$timeout = $this->httpOptions['timeout'] ?? false;
		if ( $timeout !== false ) {
			$options['timeout'] = $timeout;
		}
		if ( $postData !== null ) {
			$options['method'] = 'POST';
			$options['postData'] = $postData;
		}
		$options['userAgent'] = $this->buildUserAgentString();

		/** @var MWHttpRequest $request */
		$request = $this->httpRequestFactory->create( $url, $options, __METHOD__ );

		$request->setCallback( $callback );

		$status = $request->execute();
		if ( !$status->isOK() ) {
			throw new HttpRequestException( $status, $request );
		}

		return $request;
	}

	private function buildUserAgentString(): string {
		// TODO: Pull URL and version from ExtensionRegistry.
		return 'mw-ext-FileImporter/* (https://www.mediawiki.org/wiki/Extension:FileImporter)';
	}

}
