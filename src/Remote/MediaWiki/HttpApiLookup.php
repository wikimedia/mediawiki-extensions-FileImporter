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

	/**
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * @var \FileImporter\Services\Http\HttpRequestExecutor
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

	public function setLogger( LoggerInterface $logger ) {
		$this->logger = $logger;
	}

	/**
	 * @param string $message
	 * @param array $context
	 *
	 * @return ImportException
	 */
	private function loggedError( $message, array $context = [] ) {
		$this->logger->error( $message, $context );
		return new ImportException( $message );
	}

	/**
	 * @param SourceUrl $sourceUrl
	 *
	 * @throws ImportException
	 * @return string URL of api.php
	 */
	public function getApiUrl( SourceUrl $sourceUrl ) {
		if ( array_key_exists( $sourceUrl->getUrl(), $this->resultCache ) ) {
			return $this->resultCache[ $sourceUrl->getUrl() ];
		}

		$api = $this->actuallyGetApiUrl( $sourceUrl );
		if ( $api ) {
			$this->resultCache[$sourceUrl->getUrl()] = $api;
			return $api;
		}

		throw $this->loggedError( 'Failed to get MediaWiki API from SourceUrl' );
	}

	/**
	 * @param SourceUrl $sourceUrl
	 *
	 * @return string|null
	 */
	private function actuallyGetApiUrl( SourceUrl $sourceUrl ) {
		try {
			$req = $this->httpRequestExecutor->execute( $sourceUrl->getUrl() );
		} catch ( HttpRequestException $e ) {
			$statusCode = $e->getHttpRequest()->getStatus();
			if ( $statusCode === 404 ) {
				throw $this->loggedError( 'File not found: ' . $sourceUrl->getUrl() );
			}
			if ( $e->getStatusValue()->hasMessage( 'http-timed-out' ) ) {
				throw $this->loggedError(
					'Failed to discover API location from: "' . $sourceUrl->getUrl() . '".' .
					' Connection timed out.'
				);
			}
			throw $this->loggedError(
				'Failed to discover API location from: "' . $sourceUrl->getUrl() . '".' .
				' Status code ' . $statusCode . ', ' . $e->getMessage(),
				[
					'statusCode' => $statusCode,
					'previousMessage' => $e->getMessage(),
					'responseContent' => $e->getHttpRequest()->getContent(),
				]
			);
		}

		$document = new DOMDocument();

		libxml_use_internal_errors( true );
		$document->loadHTML( $req->getContent() );
		libxml_clear_errors();

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
