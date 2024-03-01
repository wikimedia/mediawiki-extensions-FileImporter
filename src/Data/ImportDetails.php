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

	private SourceUrl $sourceUrl;
	private LinkTarget $sourceLinkTarget;
	/** @var string|null */
	private $pageLanguage;
	private TextRevisions $textRevisions;
	private FileRevisions $fileRevisions;
	/** @var string[] */
	private $templates = [];
	/** @var string[] */
	private $categories = [];

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
	}

	public function setPageLanguage( ?string $languageCode ): void {
		$this->pageLanguage = $languageCode;
	}

	/**
	 * @param string[] $templates
	 */
	public function setTemplates( array $templates ): void {
		$this->templates = $templates;
	}

	/**
	 * @param string[] $categories
	 */
	public function setCategories( array $categories ): void {
		$this->categories = $categories;
	}

	public function getSourceLinkTarget(): LinkTarget {
		return $this->sourceLinkTarget;
	}

	/**
	 * @return string File extension. Example: 'png'
	 */
	public function getSourceFileExtension(): string {
		return pathinfo( $this->sourceLinkTarget->getText(), PATHINFO_EXTENSION );
	}

	/**
	 * @return string Filename with no namespace prefix or file extension. Example: 'Berlin'
	 */
	public function getSourceFileName(): string {
		return pathinfo( $this->sourceLinkTarget->getText(), PATHINFO_FILENAME );
	}

	/**
	 * @return string|null
	 */
	public function getPageLanguage() {
		return $this->pageLanguage;
	}

	public function getImageDisplayUrl(): string {
		return $this->fileRevisions->getLatest()->getField( 'thumburl' );
	}

	public function getSourceUrl(): SourceUrl {
		return $this->sourceUrl;
	}

	public function getTextRevisions(): TextRevisions {
		return $this->textRevisions;
	}

	public function getFileRevisions(): FileRevisions {
		return $this->fileRevisions;
	}

	/**
	 * List of templates directly or indirectly transcluded in the latest revision of the file
	 * description page.
	 *
	 * @return string[]
	 */
	public function getTemplates(): array {
		return $this->templates;
	}

	/**
	 * List of categories present in the latest revision of the file description page.
	 *
	 * @return string[]
	 */
	public function getCategories(): array {
		return $this->categories;
	}

	/**
	 * Returns a string hash based on the initial value of the object. The string must not exceed
	 * 255 bytes (255 ASCII characters or less when it contains Unicode characters that
	 * need to be UTF-8 encoded) to allow using indexes on all database systems.
	 *
	 * Can be used to see if two ImportDetails objects appear to be for the same URL and have
	 * the same number of text and file revisions.
	 * Used to detect changes to a file between the start and end of an import.
	 */
	public function getOriginalHash(): string {
		$hashes = [
			sha1( $this->sourceLinkTarget->getText() ),
			sha1( $this->sourceUrl->getUrl() ),
			sha1( (string)count( $this->getTextRevisions()->toArray() ) ),
			sha1( (string)count( $this->getFileRevisions()->toArray() ) ),
		];

		foreach ( $this->getTextRevisions()->toArray() as $textRevision ) {
			$hashes[] = $textRevision->getField( 'sha1' ) ?? '';
		}

		foreach ( $this->getFileRevisions()->toArray() as $fileRevision ) {
			$hashes[] = $fileRevision->getField( 'sha1' ) ?? '';
		}

		return sha1( implode( '|', $hashes ) );
	}

}
