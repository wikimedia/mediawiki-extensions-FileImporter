<?php


namespace FileImporter\Services;

use BagOStuff;
use IExpiringStore;
use Title;

/**
 * Save the source URL to cache so that it can be looked up from the success page.
 */
class SuccessCache {

	const CACHE_KEY = 'fileimporter_sourceurl';

	/** @var BagOStuff $cache */
	private $cache;

	public function __construct( BagOStuff $cache ) {
		$this->cache = $cache;
	}

	public function stashSourceUrl( Title $targetTitle, $url ) {
		$this->cache->set(
			$this->makeCacheKey( $targetTitle ),
			$url,
			IExpiringStore::TTL_DAY );
	}

	public function fetchSourceUrl( Title $targetTitle ) {
		return $this->cache->get(
			$this->makeCacheKey( $targetTitle ) );
	}

	private function makeCacheKey( Title $targetTitle ) {
		return $this->cache->makeKey(
			__CLASS__,
			self::CACHE_KEY,
			$targetTitle->getPrefixedDBkey() );
	}

}
