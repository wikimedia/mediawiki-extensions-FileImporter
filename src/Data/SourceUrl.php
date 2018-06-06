<?php

namespace FileImporter\Data;

use InvalidArgumentException;

/**
 * Class representing the URL passed into the extension for importing.
 *
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class SourceUrl {

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
	 * @throws InvalidArgumentException When $url is not parsable
	 */
	public function __construct( $url ) {
		$this->url = $url;
		$this->parsed = wfParseUrl( $url );
		if ( !$this->parsed ) {
			throw new InvalidArgumentException( '$url is not parsable' );
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
		return $this->parsed['host'];
	}

	public function __toString() {
		return $this->url;
	}

}
