<?php

namespace FileImporter\Data;

use MalformedTitleException;
use MediaWiki\MediaWikiServices;
use RuntimeException;
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
	 * @var string
	 */
	private $interWikiPrefix;

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
	 * @throws MalformedTitleException if title parsing failed
	 * @throws RuntimeException if Title::newFromLinkTarget returned null when given a TitleValue
	 * @return Title
	 */
	public function getTitle() {
		if ( $this->title === null ) {
			$titleParser = MediaWikiServices::getInstance()->getTitleParser();
			$titleValue = $titleParser->parseTitle( $this->getTitleText(), NS_FILE );
			$this->title = Title::newFromLinkTarget( $titleValue );

			if ( $this->title === null ) {
				throw new RuntimeException( __METHOD__ . ' failed to get a Title object.' );
			}
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
		return $this->getInitialCleanedInfoText();
	}

	/**
	 * Appends a marker to the beginning of the cleaned File Info Text indicating that
	 * it was imported using FileImporter
	 *
	 * @return string
	 */
	private function getInitialCleanedInfoText() {
		$text = $this->details->getCleanedRevisionText();
		if ( $text === null ) {
			$text = $this->getInitialFileInfoText();
		}
		return $this->addImportComment( $text );
	}

	/**
	 * @return string
	 */
	public function getInitialFileInfoText() {
		$textRevision = $this->details->getTextRevisions()->getLatest();
		return $textRevision ? $textRevision->getField( '*' ) : '';
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

}
