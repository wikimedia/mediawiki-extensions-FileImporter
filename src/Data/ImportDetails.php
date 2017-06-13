<?php

namespace FileImporter\Data;

use MediaWiki\Linker\LinkTarget;
use Title;
use Wikimedia\Assert\Assert;

class ImportDetails {

	/**
	 * @var SourceUrl
	 */
	private $sourceUrl;

	/**
	 * @var LinkTarget
	 */
	private $linkTarget;

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
	 * @var LinkTarget|null
	 */
	private $targetLinkTarget = null;

	/**
	 * @var Title|null
	 */
	private $targetTitle = null;

	/**
	 * @param SourceUrl $sourceUrl
	 * @param LinkTarget $linkTarget
	 * @param string $imageDisplayUrl
	 * @param TextRevisions $textRevisions
	 * @param FileRevisions $fileRevisions
	 */
	public function __construct(
		SourceUrl $sourceUrl,
		LinkTarget $linkTarget,
		$imageDisplayUrl,
		TextRevisions $textRevisions,
		FileRevisions $fileRevisions
	) {
		Assert::parameterType( 'string', $imageDisplayUrl, '$imageDisplayUrl' );

		$this->sourceUrl = $sourceUrl;
		$this->linkTarget = $linkTarget;
		$this->imageDisplayUrl = $imageDisplayUrl;
		$this->textRevisions = $textRevisions;
		$this->fileRevisions = $fileRevisions;
	}

	public function getOriginalLinkTarget() {
		return $this->linkTarget;
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
	 * @return LinkTarget
	 */
	public function getTargetLinkTarget() {
		return $this->targetLinkTarget !== null ? $this->targetLinkTarget : $this->linkTarget;
	}

	public function setTargetLinkTarget( LinkTarget $linkTarget ) {
		$this->targetLinkTarget = $linkTarget;
	}

	/**
	 * @return Title
	 */
	public function getTargetTitle() {
		if ( $this->targetTitle === null ) {
			$this->targetTitle = Title::newFromLinkTarget( $this->getTargetLinkTarget() );
		}
		return $this->targetTitle;
	}

	/**
	 * Returns a string hash based on the initial value of the object. The string must not exceed
	 * 255 bytes (255 ASCII characters or less when it contains Unicode characters that
	 * need to be UTF-8 encoded) to allow using indexes on all database systems.
	 *
	 * Can be used to see if two ImportDetails objects appear to be for the same URL and have
	 * the same number of text and file revisions.
	 * Used to detect changes to a file between the start and end of an import.
	 *
	 * @return string
	 */
	public function getOriginalHash() {
		$hashes = [
			sha1( $this->linkTarget->getText() ),
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
