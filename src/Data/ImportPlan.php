<?php

namespace FileImporter\Data;

use RuntimeException;
use Title;

/**
 * Planned import.
 * Data from the source site can be found in the ImportDetails object and the user requested changes
 * can be found in the ImportRequest object.
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

	public function __construct( ImportRequest $request, ImportDetails $details ) {
		$this->request = $request;
		$this->details = $details;
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
	public function getTitle() {
		if ( $this->title === null ) {
			$intendedFileName = $this->request->getIntendedName();

			if ( $intendedFileName ) {
				$fileExtension = $this->details->getSourceFileExtension();
				$intendedTitleText = $intendedFileName . '.' . $fileExtension;
				$this->title = Title::newFromText( $intendedTitleText, NS_FILE );
			} else {
				$this->title = Title::newFromLinkTarget( $this->details->getSourceLinkTarget() );
			}

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
		return pathinfo( $this->getTitle()->getText() )['filename'];
	}

	/**
	 * @return string
	 */
	public function getFileExtension() {
		return pathinfo( $this->getTitle()->getText() )['extension'];
	}

}
