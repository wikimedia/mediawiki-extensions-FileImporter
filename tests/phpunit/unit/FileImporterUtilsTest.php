<?php

namespace FileImporter\Tests;

use FileImporter\FileImporterUtils;

/**
 * @covers \FileImporter\FileImporterUtils
 *
 * @license GPL-2.0-or-later
 */
class FileImporterUtilsTest extends \MediaWikiUnitTestCase {

	public function provideHtmlSnippets() {
		return [
			'success' => [
				'<a>… <a href="#">…',
				'<a target="_blank">… <a target="_blank" href="#">…',
			],
			'tag is capitalized' => [
				'<A>…',
				'<a target="_blank">…',
			],
			'there is already a target' => [
				"<a href=\"#\"\ntarget=\"_top\">… <a>…",
				"<a href=\"#\"\ntarget=\"_top\">… <a target=\"_blank\">…",
			],
			'not an <a> tag' => [
				'<abbr>…',
				'<abbr>…',
			],
		];
	}

	/**
	 * @dataProvider provideHtmlSnippets
	 */
	public function test( string $html, string $expected ) {
		$this->assertSame( $expected, FileImporterUtils::addTargetBlankToLinks( $html ) );
	}

}
