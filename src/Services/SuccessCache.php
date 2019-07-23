<?php


namespace FileImporter\Services;

use BagOStuff;
use IExpiringStore;
use StatusValue;
use Title;

/**
 * Save the import results to cache so that they can be looked up from the success page.
 */
class SuccessCache {

	const CACHE_KEY = 'fileImporter_result';

	/** @var BagOStuff $cache */
	private $cache;

	public function __construct( BagOStuff $cache ) {
		$this->cache = $cache;
	}

	public function stashImportResult( Title $targetTitle, StatusValue $importResult ) {
		$this->cache->set(
			$this->makeCacheKey( $targetTitle ),
			$importResult,
			IExpiringStore::TTL_DAY );
	}

	/**
	 * @param Title $targetTitle
	 * @return StatusValue
	 */
	public function fetchImportResult( Title $targetTitle ) {
		return $this->cache->get(
			$this->makeCacheKey( $targetTitle ) );
	}

	private function makeCacheKey( Title $targetTitle ) {
		// TODO see if this should be fixed differently
		// @phan-suppress-next-line PhanParamTooMany
		return $this->cache->makeKey(
			__CLASS__,
			self::CACHE_KEY,
			$targetTitle->getPrefixedDBkey() );
	}

}
