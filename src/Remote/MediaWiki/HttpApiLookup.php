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
	 * @throws ImportException
	 */
	private function logAndException( $message, $context = [] ) {
		$this->logger->error( $message, $context );
		throw new ImportException( $message );
	}

	/**
	 * @param SourceUrl $sourceUrl
	 *
	 * @throws ImportException
	 * @return string URL of api.php or null
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

		$this->logAndException( 'Failed to get MediaWiki API from SourceUrl' );
		return null; // never reached
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
				$this->logAndException( 'File not found: ' . $sourceUrl->getUrl() );
			}
			if ( $e->getStatusValue()->hasMessage( 'http-timed-out' ) ) {
				$this->logAndException(
					'Failed to discover API location from: "' . $sourceUrl->getUrl() . '".' .
					' Connection timed out.'
				);
			}
			$this->logAndException(
				'Failed to discover API location from: "' . $sourceUrl->getUrl() . '".' .
				' Status code ' . $statusCode . ', ' . $e->getMessage(),
				[
					'statusCode' => $statusCode,
					'previousMessage' => $e->getMessage(),
					'responseContent' => $e->getHttpRequest()->getContent(),
				]
			);
			return null; // never reached
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
