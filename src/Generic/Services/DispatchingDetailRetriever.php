<?php

namespace FileImporter\Generic\Services;

use FileImporter\Generic\Data\TargetUrl;
use FileImporter\Generic\Exceptions\ImportTargetException;
use FileImporter\Generic\Services\DetailRetriever;

class DispatchingDetailRetriever implements DetailRetriever {

	/**
	 * @var DetailRetriever[]
	 */
	private $retrievers;

	/**
	 * @param DetailRetriever[] $retrievers
	 */
	public function __construct( array $retrievers ) {
		$this->retrievers = $retrievers;
	}

	/**
	 * @param TargetUrl $targetUrl
	 *
	 * @return DetailRetriever
	 * @throws ImportTargetException
	 */
	private function getRetrieverForTarget( TargetUrl $targetUrl ) {
		foreach ( $this->retrievers as $retriever ) {
			if ( $retriever->canGetImportDetails( $targetUrl ) ) {
				return $retriever;
			}
		}
		throw new ImportTargetException();
	}

	public function canGetImportDetails( TargetUrl $targetUrl ) {
		try {
			$this->getRetrieverForTarget( $targetUrl );
			return true;
		} catch ( ImportTargetException $e ) {
			return false;
		}
	}

	public function getImportDetails( TargetUrl $targetUrl ) {
		$importer = $this->getRetrieverForTarget( $targetUrl );
		return $importer->getImportDetails( $targetUrl );
	}

}
