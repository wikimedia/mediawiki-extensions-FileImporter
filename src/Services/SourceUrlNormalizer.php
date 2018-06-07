<?php

namespace FileImporter\Services;

use FileImporter\Data\SourceUrl;

/**
 * Base class for dedicated normalization rules that are only true for specific SourceUrls, as
 * detected by the corresponding SourceUrlChecker.
 *
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class SourceUrlNormalizer {

	/**
	 * @var callable
	 */
	private $callback;

	/**
	 * @param callable $callback that takes and returns a single SourceUrl object
	 */
	public function __construct( $callback ) {
		$this->callback = $callback;
	}

	/**
	 * @param SourceUrl $sourceUrl
	 *
	 * @return SourceUrl
	 */
	public function normalize( SourceUrl $sourceUrl ) {
		return call_user_func( $this->callback, $sourceUrl );
	}

}
