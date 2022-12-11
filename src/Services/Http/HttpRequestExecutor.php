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

	/**
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * @var HttpRequestFactory
	 */
	private $httpRequestFactory;

	/**
	 * @var array
	 */
	private $httpOptions;

	/**
	 * @var int
	 */
	private $maxFileSize;

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
		$maxFileSize
	) {
		$this->httpRequestFactory = $httpRequestFactory;
		$this->logger = new NullLogger();
		$this->maxFileSize = $maxFileSize;
		$this->httpOptions = $httpOptions;
	}

	/**
	 * @param LoggerInterface $logger
	 * @codeCoverageIgnore
	 */
	public function setLogger( LoggerInterface $logger ) {
		$this->logger = $logger;
	}

	/**
	 * @param string $url
	 * @param array $parameters
	 *
	 * @return MWHttpRequest
	 * @throws HttpRequestException
	 */
	public function execute( $url, array $parameters = [] ) {
		return $this->executeHttpRequest( wfAppendQuery( $url, $parameters ) );
	}

	/**
	 * @param string $url
	 * @param array $postData
	 *
	 * @return MWHttpRequest
	 */
	public function executePost( $url, array $postData ) {
		return $this->executeHttpRequest( $url, null, $postData );
	}

	/**
	 * @param string $url
	 * @param string $filePath
	 *
	 * @return MWHttpRequest
	 * @throws HttpRequestException
	 */
	public function executeAndSave( $url, $filePath ) {
		$chunkSaver = new FileChunkSaver( $filePath, $this->maxFileSize );
		$chunkSaver->setLogger( $this->logger );
		return $this->executeHttpRequest( $url, [ $chunkSaver, 'saveFileChunk' ] );
	}

	/**
	 * @param string $url
	 * @param callable|null $callback
	 * @param array|null $postData
	 *
	 * @return MWHttpRequest
	 * @throws HttpRequestException
	 */
	private function executeHttpRequest( $url, $callback = null, $postData = null ) {
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

	private function buildUserAgentString() {
		// TODO: Pull URL and version from ExtensionRegistry.
		return 'mw-ext-FileImporter/* (https://www.mediawiki.org/wiki/Extension:FileImporter)';
	}

}
