<?php

namespace FileImporter;

/**
 * @license GPL-2.0-or-later
 */
class FileImporterUtils {

	public static function addTargetBlankToLinks( string $html ): string {
		return preg_replace( '/<a\b(?![^<>]*\starget=)/i', '<a target="_blank"', $html );
	}

}
