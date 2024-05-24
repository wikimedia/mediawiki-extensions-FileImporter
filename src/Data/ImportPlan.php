<?php

namespace FileImporter\Data;

use DateTime;
use DateTimeZone;
use MediaWiki\Config\Config;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\MalformedTitleException;
use MediaWiki\Title\Title;
use MessageLocalizer;

/**
 * Planned import.
 * Data from the source site can be found in the ImportDetails object and the user requested changes
 * can be found in the ImportRequest object.
 *
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class ImportPlan {

	private ImportRequest $request;
	private ImportDetails $details;
	private Config $config;
	private MessageLocalizer $messageLocalizer;
	/** @var Title|null */
	private $title = null;
	/** @var Title|null */
	private $originalTitle = null;
	/** @var string */
	private $interWikiPrefix;
	/** @var string|null */
	private $cleanedLatestRevisionText;
	/** @var int */
	private $numberOfTemplateReplacements = 0;
	/** @var array<string,int> */
	private $actionStats = [];
	/** @var (int|string)[] */
	private $validationWarnings = [];
	/** @var bool */
	private $automateSourceWikiCleanUp = false;
	/** @var bool */
	private $automateSourceWikiDelete = false;

	/**
	 * ImportPlan constructor, should not be constructed directly in production code.
	 * Use an ImportPlanFactory instance.
	 */
	public function __construct(
		ImportRequest $request,
		ImportDetails $details,
		Config $config,
		MessageLocalizer $messageLocalizer,
		string $prefix
	) {
		$this->request = $request;
		$this->details = $details;
		$this->config = $config;
		$this->messageLocalizer = $messageLocalizer;
		$this->interWikiPrefix = $prefix;
	}

	public function getRequest(): ImportRequest {
		return $this->request;
	}

	public function getDetails(): ImportDetails {
		return $this->details;
	}

	public function getOriginalTitle(): Title {
		$this->originalTitle ??= Title::newFromLinkTarget( $this->details->getSourceLinkTarget() );
		return $this->originalTitle;
	}

	/**
	 * @throws MalformedTitleException
	 */
	public function getTitle(): Title {
		if ( !$this->title ) {
			$intendedFileName = $this->request->getIntendedName();
			if ( $intendedFileName !== null ) {
				$linkTarget = MediaWikiServices::getInstance()->getTitleParser()->parseTitle(
					// FIXME: will be incorrect for Codex UI
					$intendedFileName . '.' . $this->details->getSourceFileExtension(),
					NS_FILE
				);
			} else {
				$linkTarget = $this->details->getSourceLinkTarget();
			}
			$this->title = Title::newFromLinkTarget( $linkTarget );
		}

		return $this->title;
	}

	/**
	 * @throws MalformedTitleException
	 */
	public function getFileName(): string {
		return pathinfo( $this->getTitle()->getText() )['filename'];
	}

	public function getInterWikiPrefix(): string {
		return $this->interWikiPrefix;
	}

	public function getFileExtension(): string {
		return $this->details->getSourceFileExtension();
	}

	public function getFileInfoText(): string {
		$text = $this->request->getIntendedText();
		if ( $text !== null ) {
			return $text;
		}

		return $this->addImportAnnotation( $this->getCleanedLatestRevisionText() );
	}

	public function getInitialFileInfoText(): string {
		$textRevision = $this->details->getTextRevisions()->getLatest();
		return $textRevision ? $textRevision->getContent() : '';
	}

	public function getCleanedLatestRevisionText(): string {
		return $this->cleanedLatestRevisionText ?? $this->getInitialFileInfoText();
	}

	/**
	 * @param string $text
	 */
	public function setCleanedLatestRevisionText( $text ): void {
		$this->cleanedLatestRevisionText = $text;
	}

	public function getNumberOfTemplateReplacements(): int {
		return $this->numberOfTemplateReplacements;
	}

	public function setNumberOfTemplateReplacements( int $replacements ): void {
		$this->numberOfTemplateReplacements = $replacements;
	}

	public function getAutomateSourceWikiCleanUp(): bool {
		return $this->automateSourceWikiCleanUp;
	}

	public function setAutomateSourceWikiCleanUp( bool $bool ): void {
		$this->automateSourceWikiCleanUp = $bool;
	}

	public function getAutomateSourceWikiDelete(): bool {
		return $this->automateSourceWikiDelete;
	}

	public function setAutomateSourceWikiDelete( bool $bool ): void {
		$this->automateSourceWikiDelete = $bool;
	}

	public function wasFileInfoTextChanged(): bool {
		return $this->getFileInfoText() !== $this->getInitialFileInfoText();
	}

	private function addImportAnnotation( string $text ): string {
		return $this->joinWikitextChunks(
			wfMsgReplaceArgs(
				$this->config->get( 'FileImporterTextForPostImportRevision' ),
				[ $this->request->getUrl() ]
			),

			$this->messageLocalizer->msg(
				'fileimporter-post-import-revision-annotation',
				$this->request->getUrl(),
				( new DateTime( 'now', new DateTimeZone( 'UTC' ) ) )->format( 'c' )
			)->inContentLanguage()->plain(),

			$text
		);
	}

	/**
	 * Concatenate wikitext using newlines when appropriate
	 *
	 * Any empty chunks are discarded before joining.
	 *
	 * @param string|array ...$chunks Varargs of each wikitext chunk, or a
	 *  single parameter with the chunks as an array.
	 * @return string Result of concatenation.
	 */
	private function joinWikitextChunks( ...$chunks ): string {
		if ( is_array( reset( $chunks ) ) ) {
			$chunks = $chunks[0];
		}
		$chunks = array_filter(
			$chunks,
			static fn ( $wikitext ) => ( $wikitext ?? '' ) !== ''
		);
		return implode( "\n", $chunks );
	}

	public function setActionIsPerformed( string $actionKey ): void {
		$this->actionStats[$actionKey] = 1;
	}

	/**
	 * @param array<string,int> $stats
	 */
	public function setActionStats( array $stats ): void {
		$this->actionStats = $stats;
	}

	/**
	 * @return array<string,int> Array mapping string keys to optional counts. The numbers default to 1 and are
	 *  typically not really of interest.
	 */
	public function getActionStats(): array {
		return $this->actionStats;
	}

	/**
	 * @param (int|string)[] $warnings
	 */
	public function setValidationWarnings( array $warnings ): void {
		$this->validationWarnings = $warnings;
	}

	/**
	 * @param int|string $warning
	 */
	public function addValidationWarning( $warning ): void {
		$this->validationWarnings[] = $warning;
	}

	/**
	 * @return (int|string)[]
	 */
	public function getValidationWarnings(): array {
		return $this->validationWarnings;
	}

}
