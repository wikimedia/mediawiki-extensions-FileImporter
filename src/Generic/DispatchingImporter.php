<?php

namespace FileImporter\Generic;

class DispatchingImporter implements Importer {

	/**
	 * @var Importer[]
	 */
	private $services;

	/**
	 * @param Importer[] $services
	 */
	public function __construct( array $services ) {
		$this->services = $services;
	}

	/**
	 * @param TargetUrl $targetUrl
	 *
	 * @return Importer
	 * @throws ImportTargetException
	 */
	private function getServiceForTarget( TargetUrl $targetUrl ) {
		foreach ( $this->services as $service ) {
			if ( $service->canImport( $targetUrl ) ) {
				return $service;
			}
		}
		throw new ImportTargetException();
	}

	public function canImport( TargetUrl $targetUrl ) {
		try {
			$this->getServiceForTarget( $targetUrl );
			return true;
		} catch ( ImportTargetException $e ) {
			return false;
		}
	}

	public function getImportDetails( TargetUrl $targetUrl ) {
		$importer = $this->getServiceForTarget( $targetUrl );
		return $importer->getImportDetails( $targetUrl );
	}

	/**
	 * @param TargetUrl $targetUrl
	 * @param ImportAdjustments $importAdjustments
	 *
	 * @return bool success
	 */
	public function import( TargetUrl $targetUrl, ImportAdjustments $importAdjustments ) {
		$importer = $this->getServiceForTarget( $targetUrl );
		return $importer->import( $targetUrl, $importAdjustments );
	}

}
