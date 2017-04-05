<?php

namespace FileImporter\MediaWiki;

use DOMDocument;
use DOMElement;
use FileImporter\Generic\Exceptions\HttpRequestException;
use FileImporter\Generic\Exceptions\ImportException;
use FileImporter\Generic\Services\HttpRequestExecutor;
use FileImporter\Generic\Data\TargetUrl;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Lookup that can take a MediaWiki site URL and return the URL of the action API.
 */
class HttpApiLookup implements LoggerAwareInterface{

	/**
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * @var HttpRequestExecutor
	 */
	private $httpRequestExecutor;

	public function __construct( HttpRequestExecutor $httpRequestExecutor ) {
		$this->httpRequestExecutor = $httpRequestExecutor;
		$this->logger = new NullLogger();
	}

	public function setLogger( LoggerInterface $logger ) {
		$this->logger = $logger;
	}

	/**
	 * @param string $message
	 * @throws ImportException
	 */
	private function logAndException( $message ) {
		$this->logger->error( $message );
		throw new ImportException( $message );
	}

	/**
	 * @param TargetUrl $targetUrl
	 * @throws ImportException
	 * @return string URL of api.php or null
	 */
	public function getApiUrl( TargetUrl $targetUrl ) {
		try {
			$req = $this->httpRequestExecutor->execute( $targetUrl->getUrl() );
		} catch ( HttpRequestException $e ) {
			if ( $e->getStatusCode() === 404 ) {
				$this->logAndException( 'File not found: ' . $targetUrl->getUrl() );
			}
			$this->logAndException(
				'Failed to discover API location from: "' . $targetUrl->getUrl() . '".' .
				'Got status code ' . $e->getStatusCode() . '.'
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
				return str_replace( '?action=rsd', '', $editUri );
			}
		}

		$this->logAndException( 'Failed to get MediaWiki API from TargetUrl' );
		return null; // never reached
	}

}
