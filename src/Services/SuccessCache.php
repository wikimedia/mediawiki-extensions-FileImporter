<?php

namespace FileImporter\Services;

use MediaWiki\Title\Title;
use MediaWiki\User\UserIdentity;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use StatusValue;
use Wikimedia\LightweightObjectStore\ExpirationAwareness;
use Wikimedia\ObjectCache\BagOStuff;

/**
 * Save the import results to cache so that they can be looked up from the success page.
 *
 * @license GPL-2.0-or-later
 */
class SuccessCache {

	private const CACHE_KEY = 'fileImporter_result';

	public function __construct(
		private readonly BagOStuff $cache,
		private readonly LoggerInterface $logger = new NullLogger(),
	) {
	}

	/**
	 * @return bool If caching was successful or not.
	 */
	public function stashImportResult( Title $targetTitle, UserIdentity $user, StatusValue $importResult ) {
		$key = $this->makeCacheKey( $targetTitle, $user );
		$this->logger->debug( __METHOD__ . ': Import result cached at ' . $key );
		return $this->cache->set( $key, $importResult, ExpirationAwareness::TTL_DAY );
	}

	/**
	 * @return StatusValue|null
	 */
	public function fetchImportResult( Title $targetTitle, UserIdentity $user ) {
		$key = $this->makeCacheKey( $targetTitle, $user );
		$importResult = $this->cache->get( $key );
		if ( !( $importResult instanceof StatusValue ) ) {
			$this->logger->error( __METHOD__ . ': Failed to retrieve import result from ' . $key );
			return null;
		}
		return $importResult;
	}

	/**
	 * @return string
	 */
	private function makeCacheKey( Title $targetTitle, UserIdentity $user ) {
		return $this->cache->makeKey(
			__CLASS__,
			self::CACHE_KEY,
			$targetTitle->getPrefixedDBkey(),
			$user->getId()
		);
	}

}
