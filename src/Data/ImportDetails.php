<?php

namespace FileImporter\Data;

use Wikimedia\Assert\Assert;

class ImportDetails {

	/**
	 * @var SourceUrl
	 */
	private $sourceUrl;

	/**
	 * @var string
	 */
	private $titleText;

	/**
	 * @var string
	 */
	private $imageDisplayUrl;

	/**
	 * @var TextRevisions
	 */
	private $textRevisions;

	/**
	 * @var FileRevisions
	 */
	private $fileRevisions;

	/**
	 * @param SourceUrl $sourceUrl
	 * @param string $titleText
	 * @param string $imageDisplayUrl
	 * @param TextRevisions $textRevisions
	 * @param FileRevisions $fileRevisions
	 */
	public function __construct(
		SourceUrl $sourceUrl,
		$titleText,
		$imageDisplayUrl,
		TextRevisions $textRevisions,
		FileRevisions $fileRevisions
	) {
		Assert::parameterType( 'string', $titleText, '$titleText' );
		Assert::parameterType( 'string', $imageDisplayUrl, '$imageDisplayUrl' );

		$this->sourceUrl = $sourceUrl;
		$this->titleText = $titleText;
		$this->imageDisplayUrl = $imageDisplayUrl;
		$this->textRevisions = $textRevisions;
		$this->fileRevisions = $fileRevisions;
	}

	public function getPrefixedTitleText() {
		return $this->titleText;
	}

	public function getImageDisplayUrl() {
		return $this->imageDisplayUrl;
	}

	public function getSourceUrl() {
		return $this->sourceUrl;
	}

	public function getTextRevisions() {
		return $this->textRevisions;
	}

	public function getFileRevisions() {
		return $this->fileRevisions;
	}

	/**
	 * Returns a string hash based on the value of the object. The string must not exceed
	 * 255 bytes (255 ASCII characters or less when it contains Unicode characters that
	 * need to be UTF-8 encoded) to allow using indexes on all database systems.
	 *
	 * Can be used to see if two ImportDetails objects appear to be for the same URL and have
	 * the same number of text and file revisions.
	 *
	 * @return string
	 */
	public function getHash() {
		$hashes = [
			sha1( $this->sourceUrl->getUrl() ),
			sha1( count( $this->getTextRevisions()->toArray() ) ),
			sha1( count( $this->getFileRevisions()->toArray() ) ),
		];

		foreach ( $this->getTextRevisions()->toArray() as $textRevision ) {
			$hashes[] = $textRevision->getField( 'sha1' );
		}

		foreach ( $this->getFileRevisions()->toArray() as $fileRevision ) {
			$hashes[] = $fileRevision->getField( 'sha1' );
		}

		return sha1( implode( '|', $hashes ) );
	}

}
