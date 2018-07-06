<?php

namespace FileImporter\Data;

use MediaWiki\Linker\LinkTarget;
use OutOfRangeException;

/**
 * Contains the details from the source site for the import.
 *
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class ImportDetails {

	/**
	 * @var SourceUrl
	 */
	private $sourceUrl;

	/**
	 * @var LinkTarget
	 */
	private $sourceLinkTarget;

	/**
	 * @var TextRevisions
	 */
	private $textRevisions;

	/**
	 * @var FileRevisions
	 */
	private $fileRevisions;

	/**
	 * @var string
	 */
	private $cleanedRevisionText = '';

	/**
	 * @var int
	 */
	private $numberOfTemplatesReplaced;

	/**
	 * @param SourceUrl $sourceUrl
	 * @param LinkTarget $sourceLinkTarget
	 * @param TextRevisions $textRevisions
	 * @param FileRevisions $fileRevisions
	 * @param int $numOfReplacedTemplates
	 */
	public function __construct(
		SourceUrl $sourceUrl,
		LinkTarget $sourceLinkTarget,
		TextRevisions $textRevisions,
		FileRevisions $fileRevisions,
		$numOfReplacedTemplates = 0
	) {
		$this->sourceUrl = $sourceUrl;
		$this->sourceLinkTarget = $sourceLinkTarget;
		$this->textRevisions = $textRevisions;
		$this->fileRevisions = $fileRevisions;
		$this->numberOfTemplatesReplaced = $numOfReplacedTemplates;
	}

	/**
	 * @param string $text
	 */
	public function setCleanedRevisionText( $text ) {
		$this->cleanedRevisionText = $text;
	}

	/**
	 * @return LinkTarget
	 */
	public function getSourceLinkTarget() {
		return $this->sourceLinkTarget;
	}

	/**
	 * @return string File extension. Example: 'png'
	 */
	public function getSourceFileExtension() {
		return pathinfo( $this->sourceLinkTarget->getText(), PATHINFO_EXTENSION );
	}

	/**
	 * @return string Filename with no namespace prefix or file extension. Example: 'Berlin'
	 */
	public function getSourceFileName() {
		return pathinfo( $this->sourceLinkTarget->getText(), PATHINFO_FILENAME );
	}

	/**
	 * @throws OutOfRangeException when no file revisions have been provided
	 * @return string
	 */
	public function getImageDisplayUrl() {
		$latest = $this->fileRevisions->getLatest();

		if ( !$latest ) {
			throw new OutOfRangeException( 'There is no latest file revision' );
		}

		return $latest->getField( 'thumburl' );
	}

	/**
	 * @return SourceUrl
	 */
	public function getSourceUrl() {
		return $this->sourceUrl;
	}

	/**
	 * @return TextRevisions
	 */
	public function getTextRevisions() {
		return $this->textRevisions;
	}

	/**
	 * @return FileRevisions
	 */
	public function getFileRevisions() {
		return $this->fileRevisions;
	}

	/**
	 * @return int
	 */
	public function getNumberOfTemplatesReplaced() {
		return $this->numberOfTemplatesReplaced;
	}

	/**
	 * @return string
	 */
	public function getCleanedRevisionText() {
		return $this->cleanedRevisionText;
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
			sha1( $this->sourceLinkTarget->getText() ),
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
