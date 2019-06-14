<?php

namespace FileImporter\Data;

use FileImporter\Exceptions\InvalidArgumentException;
use FileImporter\Exceptions\LocalizedImportException;
use Wikimedia\Assert\Assert;

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
	 * @throws InvalidArgumentException|LocalizedImportException
	 */
	public function __construct(
		$url,
		$intendedName = null,
		$intendedText = null,
		$intendedSummary = null,
		$importDetailsHash = ''
	) {
		Assert::parameterType( 'string', $url, '$url' );
		Assert::parameterType( 'string|null', $intendedName, '$intendedName' );
		Assert::parameterType( 'string|null', $intendedText, '$intendedText' );
		Assert::parameterType( 'string|null', $intendedSummary, '$intendedSummary' );
		Assert::parameterType( 'string', $importDetailsHash, '$importDetailsHash' );

		try {
			$this->url = new SourceUrl( $url );
		} catch ( InvalidArgumentException $e ) {
			throw new LocalizedImportException( [ 'fileimporter-cantparseurl', $url ] );
		}

		if ( $intendedText !== null ) {
			/**
			 * White spaces and carriage returns are trimmed (inline with EditPage) so that we can
			 * actually detect if the text to be saved has changed at all.
			 */
			$intendedText = $this->removeCarriageReturns(
				$this->removeTrailingWhitespaces( $intendedText )
			);
		}

		$this->intendedName = $intendedName;
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
	 * @return null|string
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

	/**
	 * @param string $text
	 * @return string
	 */
	private function removeTrailingWhitespaces( $text ) {
		return rtrim( $text );
	}

	/**
	 * @param string $text
	 * @return string
	 */
	private function removeCarriageReturns( $text ) {
		return str_replace( "\r", '', $text );
	}

}
