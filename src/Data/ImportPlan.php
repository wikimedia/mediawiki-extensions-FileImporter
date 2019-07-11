<?php

namespace FileImporter\Data;

use MalformedTitleException;
use MediaWiki\MediaWikiServices;
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
	 * @var array
	 */
	private $actionStats = [];

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
	 * @param string $prefix
	 */
	public function __construct( ImportRequest $request, ImportDetails $details, $prefix ) {
		$this->request = $request;
		$this->details = $details;
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
	 * @return string
	 */
	public function getTitleText() {
		if ( $this->title === null ) {
			$intendedFileName = $this->request->getIntendedName();
			if ( $intendedFileName ) {
				return $intendedFileName . '.' . $this->details->getSourceFileExtension();
			} else {
				return $this->details->getSourceLinkTarget()->getText();
			}
		}
		return $this->title->getText();
	}

	/**
	 * @param string $titleText
	 * @throws MalformedTitleException if title parsing failed
	 * @return Title
	 */
	private function getTitleFromText( $titleText ) {
		$titleParser = MediaWikiServices::getInstance()->getTitleParser();
		$titleValue = $titleParser->parseTitle( $titleText, NS_FILE );
		return Title::newFromLinkTarget( $titleValue );
	}

	/**
	 * @return Title
	 */
	public function getOriginalTitle() {
		if ( $this->originalTitle === null ) {
			$this->originalTitle = $this->getTitleFromText(
				$this->details->getSourceLinkTarget()->getText()
			);
		}

		return $this->originalTitle;
	}

	/**
	 * @return Title
	 */
	public function getTitle() {
		if ( $this->title === null ) {
			$this->title = $this->getTitleFromText(
				$this->getTitleText()
			);
		}

		return $this->title;
	}

	/**
	 * @return string
	 */
	public function getFileName() {
		return pathinfo( $this->getTitleText() )['filename'];
	}

	/**
	 * @return string
	 */
	public function getInterWikiPrefix() {
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

		return $this->addImportComment( $this->getCleanedLatestRevisionText() );
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
	private function addImportComment( $text ) {
		$config = MediaWikiServices::getInstance()->getMainConfig();

		return wfMsgReplaceArgs(
				$config->get( 'FileImporterTextForPostImportRevision' ), [ $this->request->getUrl() ]
			) . $text;
	}

	/**
	 * @param string $actionKey
	 */
	public function setActionIsPerformed( $actionKey ) {
		$this->actionStats[$actionKey] = 1;
	}

	/**
	 * @param array $stats
	 */
	public function setActionStats( array $stats ) {
		$this->actionStats = $stats;
	}

	/**
	 * @return array
	 */
	public function getActionStats() {
		return $this->actionStats;
	}

}
