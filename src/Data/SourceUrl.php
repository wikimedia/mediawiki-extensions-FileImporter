<?php

namespace FileImporter\Data;

use InvalidArgumentException;

/**
 * Class representing the URL passed into the extension for importing.
 */
class SourceUrl {

	/**
	 * @var string
	 */
	private $url;

	/**
	 * @var string[]|null|bool
	 */
	private $parsed;

	/**
	 * @param string $url
	 * @throws InvalidArgumentException when url is not parsable
	 */
	public function __construct( $url ) {
		$this->url = $url;
		if ( !$this->isParsable() ) {
			throw new InvalidArgumentException( '$url is not parsable' );
		}
	}

	/**
	 * @return string
	 */
	public function getUrl() {
		return $this->url;
	}

	/**
	 * @return string[]|false false if the URL is not parsable
	 */
	public function getParsedUrl() {
		if ( $this->parsed === null ) {
			$this->parsed = wfParseUrl( $this->url );
		}
		return $this->parsed;
	}

	/**
	 * @return bool
	 */
	public function isParsable() {
		return (bool)$this->getParsedUrl();
	}

	/**
	 * @return string|bool false if the URL is not parsable
	 */
	public function getHost() {
		// TODO configurable host normalization? Using configurable regexes?
		// For Wikimedia this will enabled normalizing of .m. and .zero. in hosts
		if ( $this->isParsable() ) {
			return $this->parsed['host'];
		}
		return false;
	}

	public function __toString() {
		return $this->url;
	}

}
