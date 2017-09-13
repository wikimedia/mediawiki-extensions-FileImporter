<?php

namespace FileImporter\Services;

use FileImporter\Data\SourceUrl;

class SourceUrlNormalizer {

	/**
	 * @var callable|null
	 */
	private $callback;

	/**
	 * @param callable|null $callback that takes and returns a single SourceUrl object
	 */
	public function __construct( $callback = null ) {
		$this->callback = $callback;
	}

	/**
	 * @param SourceUrl $sourceUrl
	 *
	 * @return SourceUrl
	 */
	public function normalize( SourceUrl $sourceUrl ) {
		if ( $this->callback === null ) {
			return $sourceUrl;
		}
		return call_user_func( $this->callback, $sourceUrl );
	}

}
