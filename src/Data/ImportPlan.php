<?php

namespace FileImporter\Data;

use Config;
use DateTime;
use DateTimeZone;
use MalformedTitleException;
use MediaWiki\MediaWikiServices;
use MessageLocalizer;
use Title;

/**
 * Planned import.
 * Data from the source site can be found in the ImportDetails object and the user requested changes
 * can be found in the ImportRequest object.
 *
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class ImportPlan {

	/**
	 * @var ImportRequest
	 */
	private $request;

	/**
	 * @var ImportDetails
	 */
	private $details;

	/**
	 * @var Config
	 */
	private $config;

	/**
	 * @var MessageLocalizer
	 */
	private $messageLocalizer;

	/**
	 * @var Title|null
	 */
	private $title = null;

	/**
	 * @var Title|null
	 */
	private $originalTitle = null;

	/**
	 * @var string
	 */
	private $interWikiPrefix;

	/**
	 * @var string|null
	 */
	private $cleanedLatestRevisionText;

	/**
	 * @var int
	 */
	private $numberOfTemplateReplacements = 0;

	/**
	 * @var int[]
	 */
	private $actionStats = [];

	/**
	 * @var int[]
	 */
	private $validationWarnings = [];

	/**
	 * @var bool
	 */
	private $automateSourceWikiCleanUp = false;

	/**
	 * @var bool
	 */
	private $automateSourceWikiDelete = false;

	/**
	 * ImportPlan constructor, should not be constructed directly in production code.
	 * Use an ImportPlanFactory instance.
	 *
	 * @param ImportRequest $request
	 * @param ImportDetails $details
	 * @param Config $config
	 * @param MessageLocalizer $messageLocalizer
	 * @param string $prefix
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

	/**
	 * @return ImportRequest
	 */
	public function getRequest() {
		return $this->request;
	}

	/**
	 * @return ImportDetails
	 */
	public function getDetails() {
		return $this->details;
	}

	/**
	 * @return Title
	 */
	public function getOriginalTitle() {
		if ( !$this->originalTitle ) {
			$this->originalTitle = Title::newFromLinkTarget( $this->details->getSourceLinkTarget() );
		}

		return $this->originalTitle;
	}

	/**
	 * @return Title
	 * @throws MalformedTitleException
	 */
	public function getTitle() {
		if ( !$this->title ) {
			$intendedFileName = $this->request->getIntendedName();
			if ( $intendedFileName !== null ) {
				$linkTarget = MediaWikiServices::getInstance()->getTitleParser()->parseTitle(
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
	 * @return string
	 */
	public function getFileName() {
		return pathinfo( $this->getTitle()->getText() )['filename'];
	}

	/**
	 * @return string
	 */
	public function getInterWikiPrefix(): string {
		return $this->interWikiPrefix;
	}

	/**
	 * @return string
	 */
	public function getFileExtension() {
		return $this->details->getSourceFileExtension();
	}

	/**
	 * @return string
	 */
	public function getFileInfoText() {
		$text = $this->request->getIntendedText();
		if ( $text !== null ) {
			return $text;
		}

		return $this->addImportAnnotation( $this->getCleanedLatestRevisionText() );
	}

	/**
	 * @return string
	 */
	public function getInitialFileInfoText() {
		$textRevision = $this->details->getTextRevisions()->getLatest();
		return $textRevision ? $textRevision->getField( '*' ) : '';
	}

	/**
	 * @return string
	 */
	public function getCleanedLatestRevisionText() {
		return $this->cleanedLatestRevisionText ?? $this->getInitialFileInfoText();
	}

	/**
	 * @param string $text
	 */
	public function setCleanedLatestRevisionText( $text ) {
		$this->cleanedLatestRevisionText = $text;
	}

	/**
	 * @return int
	 */
	public function getNumberOfTemplateReplacements() {
		return $this->numberOfTemplateReplacements;
	}

	/**
	 * @param int $replacements
	 */
	public function setNumberOfTemplateReplacements( $replacements ) {
		$this->numberOfTemplateReplacements = $replacements;
	}

	/**
	 * @return bool
	 */
	public function getAutomateSourceWikiCleanUp() {
		return $this->automateSourceWikiCleanUp;
	}

	/**
	 * @param bool $bool
	 */
	public function setAutomateSourceWikiCleanUp( $bool ) {
		$this->automateSourceWikiCleanUp = $bool;
	}

	/**
	 * @return bool
	 */
	public function getAutomateSourceWikiDelete() {
		return $this->automateSourceWikiDelete;
	}

	/**
	 * @param bool $bool
	 */
	public function setAutomateSourceWikiDelete( $bool ) {
		$this->automateSourceWikiDelete = $bool;
	}

	/**
	 * @return bool
	 */
	public function wasFileInfoTextChanged() {
		return $this->getFileInfoText() !== $this->getInitialFileInfoText();
	}

	/**
	 * @param string $text
	 * @return string
	 */
	private function addImportAnnotation( $text ) {
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
	private function joinWikitextChunks( ...$chunks ) {
		if ( is_array( reset( $chunks ) ) ) {
			$chunks = $chunks[0];
		}
		$chunks = array_filter(
			$chunks,
			static function ( $wikitext ) {
				return $wikitext !== null && $wikitext !== '';
			}
		);
		return implode( "\n", $chunks );
	}

	/**
	 * @param string $actionKey
	 */
	public function setActionIsPerformed( string $actionKey ) {
		$this->actionStats[$actionKey] = 1;
	}

	/**
	 * @param int[] $stats
	 */
	public function setActionStats( array $stats ) {
		$this->actionStats = $stats;
	}

	/**
	 * @return int[] Array mapping string keys to optional counts. The numbers default to 1 and are
	 *  typically not really of interest.
	 */
	public function getActionStats(): array {
		return $this->actionStats;
	}

	/**
	 * @param int[] $warnings
	 */
	public function setValidationWarnings( array $warnings ) {
		$this->validationWarnings = $warnings;
	}

	/**
	 * @param int $warning
	 */
	public function addValidationWarning( int $warning ) {
		$this->validationWarnings[] = $warning;
	}

	/**
	 * @return int[]
	 */
	public function getValidationWarnings(): array {
		return $this->validationWarnings;
	}

}
