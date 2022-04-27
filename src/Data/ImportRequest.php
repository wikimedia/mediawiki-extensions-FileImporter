<?php

namespace FileImporter\Data;

use FileImporter\Exceptions\ImportException;
use FileImporter\Exceptions\InvalidArgumentException;
use FileImporter\Exceptions\LocalizedImportException;

/**
 * Alterations to be made usually provided by a user.
 *
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class ImportRequest {

	/**
	 * @var SourceUrl
	 */
	private $url;

	/**
	 * @var null|string
	 */
	private $intendedName;

	/**
	 * @var null|string
	 */
	private $intendedText;

	/**
	 * @var null|string
	 */
	private $intendedSummary;

	/**
	 * @var string
	 */
	private $importDetailsHash;

	/**
	 * @param string $url
	 * @param string|null $intendedName null for no intended change
	 * @param string|null $intendedText null for no intended change
	 * @param string|null $intendedSummary null for no intended change
	 * @param string $importDetailsHash
	 *
	 * @throws ImportException when the provided URL can't be parsed
	 */
	public function __construct(
		string $url,
		string $intendedName = null,
		string $intendedText = null,
		string $intendedSummary = null,
		string $importDetailsHash = ''
	) {
		try {
			$this->url = new SourceUrl( $url );
		} catch ( InvalidArgumentException $e ) {
			throw new LocalizedImportException( [ 'fileimporter-cantparseurl', $url ], $e );
		}

		if ( $intendedName !== null ) {
			$intendedName = trim( $intendedName );
		}

		if ( $intendedText !== null ) {
			/**
			 * White spaces and carriage returns are trimmed (inline with EditPage) so that we can
			 * actually detect if the text to be saved has changed at all.
			 */
			// TODO: This is identical to TextContent::normalizeLineEndings(). Just call that?
			$intendedText = str_replace( [ "\r\n", "\r" ], "\n", rtrim( $intendedText ) );
		}

		$this->intendedName = $intendedName === '' ? null : $intendedName;
		$this->intendedText = $intendedText;
		$this->intendedSummary = $intendedSummary;
		$this->importDetailsHash = $importDetailsHash;
	}

	/**
	 * @return SourceUrl
	 */
	public function getUrl() {
		return $this->url;
	}

	/**
	 * @return string|null Guaranteed to be a trimmed, non-empty string, or null
	 */
	public function getIntendedName() {
		return $this->intendedName;
	}

	/**
	 * @return null|string
	 */
	public function getIntendedText() {
		return $this->intendedText;
	}

	/**
	 * @return null|string
	 */
	public function getIntendedSummary() {
		return $this->intendedSummary;
	}

	/**
	 * @see \FileImporter\Data\ImportDetails::getOriginalHash
	 *
	 * @return string
	 */
	public function getImportDetailsHash() {
		return $this->importDetailsHash;
	}

}
