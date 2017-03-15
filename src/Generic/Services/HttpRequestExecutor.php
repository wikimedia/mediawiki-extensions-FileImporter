<?php

namespace FileImporter\Generic\Services;

use FileImporter\Generic\Exceptions\HttpRequestException;
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

	public function __construct() {
		$this->requestFactoryCallable = [ MWHttpRequest::class, 'factory' ];
		$this->logger = new NullLogger();
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
	 * @return MWHttpRequest
	 * @throws HttpRequestException
	 */
	public function execute( $url ) {
		/** @var MWHttpRequest $request */
		$request = call_user_func(
			$this->requestFactoryCallable,
			$url,
			[
				'logger' => $this->logger,
				'followRedirects' => true,
			],
			__METHOD__
		);

		$status = $request->execute();
		if ( !$status->isOK() ) {
			throw new HttpRequestException();
		}

		return $request;
	}

}
