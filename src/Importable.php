<?php

namespace FileImporter;

interface Importable {

	/**
	 * @return string The text to be used as the title.
	 * e.g. in Mediawiki File:Berlin would simply be "Berlin"
	 */
	public function getTitle();

	/**
	 * @return string A URL that can be used to display the image
	 * This could be a URL on an external site.
	 */
	public function getImageUrl();

	/**
	 * @return string The URL that the import was initiated using.
	 */
	public function getTargetUrl();

}
