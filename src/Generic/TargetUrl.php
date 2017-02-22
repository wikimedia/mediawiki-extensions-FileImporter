<?php

namespace FileImporter\Generic;

class TargetUrl {

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
	 */
	public function __construct( $url ) {
		$this->url = $url;
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

}
