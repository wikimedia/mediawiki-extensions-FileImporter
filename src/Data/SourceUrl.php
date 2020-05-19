<?php

namespace FileImporter\Data;

use FileImporter\Exceptions\InvalidArgumentException;

/**
 * Class representing a (possibly unnormalized) URL passed into the extension for importing. Can be
 * any URL (e.g. a Flickr image), not necessarily pointing to a MediaWiki.
 *
 * Basic normalization that is true for any URL can be done here (e.g. Unicode normalization).
 * Normalizations for specific sources (as detected by dedicated SourceUrlCheckers) need to be done
 * in dedicated SourceUrlNormalizers.
 *
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class SourceUrl {

	private const ERROR_SOURCE_URL_UNPARSEABLE = 'sourceUrlUnparseable';

	/**
	 * @var string
	 */
	private $url;

	/**
	 * @var string[]
	 */
	private $parsed;

	/**
	 * @param string $url For example, https://en.wikipedia.org/wiki/File:Foo.JPG
	 *
	 * @throws InvalidArgumentException When $url is not parsable
	 */
	public function __construct( $url ) {
		$this->url = trim( $url );
		$this->parsed = wfParseUrl( $this->url );
		if ( !$this->parsed ) {
			throw new InvalidArgumentException( '$url is not parsable',
				self::ERROR_SOURCE_URL_UNPARSEABLE );
		}
	}

	/**
	 * @return string The raw URL
	 */
	public function getUrl() {
		return $this->url;
	}

	/**
	 * @return string[] Parsed URL array provided by wfParseUrl
	 */
	public function getParsedUrl() {
		return $this->parsed;
	}

	/**
	 * @return string The host, for example "en.wikipedia.org"
	 */
	public function getHost() {
		return strtolower( $this->parsed['host'] );
	}

	public function __toString() {
		return $this->url;
	}

}
