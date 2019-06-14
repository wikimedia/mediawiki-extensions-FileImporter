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
	 * @param string $message
	 * @param array $context
	 * @param string $code
	 *
	 * @return ImportException
	 */
	private function loggedError( $message, array $context = [], $code ) {
		$this->logger->error( $message, $context );
		return new ImportException( $message, $code );
	}

	/**
	 * @param SourceUrl $sourceUrl A URL that points to any editable HTML page in any MediaWiki
	 *  wiki. The page is expected to contain a <link rel="EditURI" href="…"> element.
	 *
	 * @throws ImportException
	 * @return string URL of api.php
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
			'Failed to get MediaWiki API from SourceUrl',
			[],
			self::ERROR_CANNOT_FIND_SOURCE_API );
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
				// TODO: Localize this message?
				$msg = 'File not found: ' . $pageUrl;
			} else {
				// TODO: Localize this message?
				$msg = 'Failed to discover API location from: "' . $pageUrl . '".';
				if ( $statusCode !== 200 ) {
					// TODO: Localize this message?
					$msg .= " HTTP status code $statusCode.";
				}
				if ( $error ) {
					$msg .= ' ' . wfMessage( $error['message'], $error['params'] )->plain();
				}
			}

			throw $this->loggedError(
				htmlspecialchars( $msg ),
				[
					'statusCode' => $statusCode,
					'previousMessage' => $error ? $error['message'] : '',
					'responseContent' => $ex->getHttpRequest()->getContent(),
				],
				$statusCode
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
				return $api;
			}
		}

		return null;
	}

}
