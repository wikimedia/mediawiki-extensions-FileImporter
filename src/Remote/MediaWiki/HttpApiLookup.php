<?php

namespace FileImporter\Remote\MediaWiki;

use DOMDocument;
use DOMElement;
use FileImporter\Data\SourceUrl;
use FileImporter\Exceptions\HttpRequestException;
use FileImporter\Exceptions\ImportException;
use FileImporter\Services\Http\HttpRequestExecutor;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Lookup that can take a MediaWiki site URL and return the URL of the action API.
 * This service caches APIs that have been found for the lifetime of the object.
 *
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class HttpApiLookup implements LoggerAwareInterface {

	const ERROR_CANNOT_FIND_SOURCE_API = 'noSourceApiFound';

	/**
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * @var HttpRequestExecutor
	 */
	private $httpRequestExecutor;

	/**
	 * @var string[] url => apiUrl
	 */
	private $resultCache = [];

	public function __construct( HttpRequestExecutor $httpRequestExecutor ) {
		$this->httpRequestExecutor = $httpRequestExecutor;
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
	 * @param string $code
	 * @param string $message
	 * @param array $context
	 *
	 * @return ImportException
	 */
	private function loggedError( $code, $message, array $context = [] ) {
		$this->logger->error( $message, $context );
		return new ImportException( $message, $code );
	}

	/**
	 * @param SourceUrl $sourceUrl A URL that points to any editable HTML page in any MediaWiki
	 *  wiki. The page is expected to contain a <link rel="EditURI" href="…"> element.
	 *
	 * @return string URL of api.php
	 * @throws ImportException
	 */
	public function getApiUrl( SourceUrl $sourceUrl ) {
		$pageUrl = $sourceUrl->getUrl();

		if ( array_key_exists( $pageUrl, $this->resultCache ) ) {
			return $this->resultCache[$pageUrl];
		}

		$api = $this->actuallyGetApiUrl( $pageUrl );
		if ( $api ) {
			$this->resultCache[$pageUrl] = $api;
			return $api;
		}

		throw $this->loggedError(
			self::ERROR_CANNOT_FIND_SOURCE_API,
			'Failed to get MediaWiki API from SourceUrl'
		);
	}

	/**
	 * @param string $pageUrl
	 *
	 * @return string|null
	 */
	private function actuallyGetApiUrl( $pageUrl ) {
		try {
			$req = $this->httpRequestExecutor->execute( $pageUrl );
		} catch ( HttpRequestException $ex ) {
			$statusCode = $ex->getHttpRequest()->getStatus();
			$errors = $ex->getStatusValue()->getErrors();
			$error = reset( $errors );

			if ( $statusCode === 404 ) {
				$msg = wfMessage( 'fileimporter-api-file-notfound', $pageUrl )->plain();
			} else {
				$msg = wfMessage( 'fileimporter-api-failedtofindapi', $pageUrl )->plain();
				if ( $statusCode !== 200 ) {
					$msg .= ' ' . wfMessage( 'fileimporter-http-statuscode', $statusCode )->plain();

				}
				if ( $error ) {
					$msg .= ' ' . wfMessage( $error['message'], $error['params'] )->plain();
				}
			}

			throw $this->loggedError(
				(string)$statusCode,
				$msg,
				[
					'statusCode' => $statusCode,
					'previousMessage' => $error ? $error['message'] : '',
					'responseContent' => $ex->getHttpRequest()->getContent(),
				]
			);
		}

		$document = new DOMDocument();

		$oldXmlErrorUsage = libxml_use_internal_errors( true );

		$document->loadHTML( $req->getContent() );

		libxml_clear_errors();
		libxml_use_internal_errors( $oldXmlErrorUsage );

		$elements = $document->getElementsByTagName( 'link' );
		foreach ( $elements as $element ) {
			/** @var DOMElement $element */
			if ( $element->getAttribute( 'rel' ) === 'EditURI' ) {
				$editUri = $element->getAttribute( 'href' );
				$api = str_replace( '?action=rsd', '', $editUri );
				// Always prefer HTTPS because of (optional) edit/delete requests, see T228851
				return wfExpandUrl( $api, PROTO_HTTPS );
			}
		}

		$this->logger->error(
			'Failed to discover API location from: "' . $pageUrl . '".',
			[
				'responseContent' => $req->getContent(),
			]
		);

		return null;
	}

}
