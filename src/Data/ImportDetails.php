<?php

namespace FileImporter\Data;

use MediaWiki\Linker\LinkTarget;

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
	 * @var string[]
	 */
	private $templates = [];

	/**
	 * @var string[]
	 */
	private $categories = [];

	/**
	 * @var string|null
	 */
	private $cleanedRevisionText;

	/**
	 * @var int
	 */
	private $numberOfTemplatesReplaced = 0;

	/**
	 * @param SourceUrl $sourceUrl
	 * @param LinkTarget $sourceLinkTarget
	 * @param TextRevisions $textRevisions
	 * @param FileRevisions $fileRevisions
	 */
	public function __construct(
		SourceUrl $sourceUrl,
		LinkTarget $sourceLinkTarget,
		TextRevisions $textRevisions,
		FileRevisions $fileRevisions
	) {
		$this->sourceUrl = $sourceUrl;
		$this->sourceLinkTarget = $sourceLinkTarget;
		$this->textRevisions = $textRevisions;
		$this->fileRevisions = $fileRevisions;

		$textRevision = $textRevisions->getLatest();
		$this->cleanedRevisionText = $textRevision ? $textRevision->getField( '*' ) : null;
	}

	/**
	 * @param string[] $templates
	 */
	public function setTemplates( array $templates ) {
		$this->templates = $templates;
	}

	/**
	 * @param string[] $categories
	 */
	public function setCategories( array $categories ) {
		$this->categories = $categories;
	}

	/**
	 * @param string $text
	 */
	public function setCleanedRevisionText( $text ) {
		$this->cleanedRevisionText = $text;
	}

	/**
	 * @param int $replacements
	 */
	public function setNumberOfTemplatesReplaced( $replacements ) {
		$this->numberOfTemplatesReplaced = $replacements;
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
	 * @return string
	 */
	public function getImageDisplayUrl() {
		return $this->fileRevisions->getLatest()->getField( 'thumburl' );
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
	 * List of templates directly or indirectly transcluded in the latest revision of the file
	 * description page.
	 *
	 * @return string[]
	 */
	public function getTemplates() {
		return $this->templates;
	}

	/**
	 * List of categories present in the latest revision of the file description page.
	 *
	 * @return string[]
	 */
	public function getCategories() {
		return $this->categories;
	}

	/**
	 * FIXME: This field must be moved to the ImportPlan! It's only needed in contexts that have
	 * access to the ImportPlan!
	 *
	 * @return int
	 */
	public function getNumberOfTemplatesReplaced() {
		return $this->numberOfTemplatesReplaced;
	}

	/**
	 * FIXME: This field must be moved to the ImportPlan! It's only needed in contexts that have
	 * access to the ImportPlan!
	 *
	 * @return string|null
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
