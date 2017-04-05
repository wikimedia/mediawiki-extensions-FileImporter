<?php

namespace FileImporter\Generic\Data;

use Wikimedia\Assert\Assert;

class ImportDetails {

	/**
	 * @var TargetUrl
	 */
	private $targetUrl;

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
	 * @param TargetUrl $targetUrl
	 * @param string $titleText
	 * @param string $imageDisplayUrl
	 * @param TextRevisions $textRevisions
	 * @param FileRevisions $fileRevisions
	 */
	public function __construct(
		TargetUrl $targetUrl,
		$titleText,
		$imageDisplayUrl,
		TextRevisions $textRevisions,
		FileRevisions $fileRevisions
	) {
		Assert::parameterType( 'string', $titleText, '$titleText' );
		Assert::parameterType( 'string', $imageDisplayUrl, '$imageDisplayUrl' );

		$this->targetUrl = $targetUrl;
		$this->titleText = $titleText;
		$this->imageDisplayUrl = $imageDisplayUrl;
		$this->textRevisions = $textRevisions;
		$this->fileRevisions = $fileRevisions;
	}

	public function getTitleText() {
		return $this->titleText;
	}

	public function getImageDisplayUrl() {
		return $this->imageDisplayUrl;
	}

	public function getTargetUrl() {
		return $this->targetUrl;
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
			sha1( $this->targetUrl->getUrl() ),
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
