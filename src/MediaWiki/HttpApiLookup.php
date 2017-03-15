<?php

namespace FileImporter\MediaWiki;

use DOMDocument;
use DOMElement;
use FileImporter\Generic\Exceptions\ImportException;
use FileImporter\Generic\HttpRequestExecutor;
use FileImporter\Generic\TargetUrl;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

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
		$this->logger->error( 'Request to get MediaWiki API from TargetUrl failed' );
		throw new ImportException( 'Request to get MediaWiki API from TargetUrl failed' );
	}

	/**
	 * @param TargetUrl $targetUrl
	 * @return string URL of api.php or null
	 */
	public function getApiUrl( TargetUrl $targetUrl ) {
		// TODO catch exceptions and do something
		$req = $this->httpRequestExecutor->execute( $targetUrl->getUrl() );

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
