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

	public function getUrl() {
		return $this->url;
	}

	public function getParsedUrl() {
		if ( $this->parsed === null ) {
			$this->parsed = wfParseUrl( $this->url );
		}
		return $this->parsed;
	}

	public function isParsable() {
		return (bool)$this->getParsedUrl();
	}

}
