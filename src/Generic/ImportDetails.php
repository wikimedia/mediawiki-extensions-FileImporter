<?php

namespace FileImporter\Generic;

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

	public function __construct(
		TargetUrl $targetUrl,
		$titleText,
		$imageDisplayUrl
	) {
		$this->targetUrl = $targetUrl;
		$this->titleText = $titleText;
		$this->imageDisplayUrl = $imageDisplayUrl;
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

}
