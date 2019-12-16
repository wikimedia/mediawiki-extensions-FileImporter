<?php

namespace FileImporter\Services;

use BagOStuff;
use IExpiringStore;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use StatusValue;
use Title;

/**
 * Save the import results to cache so that they can be looked up from the success page.
 */
class SuccessCache {

	const CACHE_KEY = 'fileImporter_result';

	/** @var BagOStuff */
	private $cache;

	/** @var LoggerInterface */
	private $logger;

	public function __construct( BagOStuff $cache, LoggerInterface $logger = null ) {
		$this->cache = $cache;
		$this->logger = $logger ?: new NullLogger();
	}

	/**
	 * @param Title $targetTitle
	 * @param StatusValue $importResult
	 *
	 * @return bool If caching was successfull or not.
	 */
	public function stashImportResult( Title $targetTitle, StatusValue $importResult ) {
		$key = $this->makeCacheKey( $targetTitle );
		$this->logger->debug( __METHOD__ . ': Import result cached at ' . $key );
		return $this->cache->set( $key, $importResult, IExpiringStore::TTL_DAY );
	}

	/**
	 * @param Title $targetTitle
	 *
	 * @return StatusValue|null
	 */
	public function fetchImportResult( Title $targetTitle ) {
		$key = $this->makeCacheKey( $targetTitle );
		$importResult = $this->cache->get( $key );
		if ( !( $importResult instanceof StatusValue ) ) {
			$this->logger->error( __METHOD__ . ': Failed to retrieve import result from ' . $key );
			return null;
		}
		return $importResult;
	}

	/**
	 * @param Title $targetTitle
	 *
	 * @return string
	 */
	private function makeCacheKey( Title $targetTitle ) {
		return $this->cache->makeKey(
			__CLASS__,
			self::CACHE_KEY,
			$targetTitle->getPrefixedDBkey()
		);
	}

}
