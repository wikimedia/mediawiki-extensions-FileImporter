<?php

namespace FileImporter\Services\Wikitext;

use FileImporter\Remote\MediaWiki\MediaWikiSourceUrlParser;
use Language;
use MediaWiki\MediaWikiServices;
use NamespaceInfo;
use TitleParser;

/**
 * @license GPL-2.0-or-later
 * @author Thiemo Kreuz
 */
class WikiLinkParserFactory {
	use MediaWikiSourceUrlParser;

	/**
	 * @var TitleParser
	 */
	private $parser;

	/**
	 * @var NamespaceInfo
	 */
	private $namespaceInfo;

	public function __construct() {
		$services = MediaWikiServices::getInstance();
		$this->parser = $services->getTitleParser();
		$this->namespaceInfo = $services->getNamespaceInfo();
	}

	/**
	 * @param Language|null $sourceLanguage
	 * @param string $interWikiPrefix
	 *
	 * @return WikiLinkParser
	 */
	public function getWikiLinkParser( Language $sourceLanguage = null, $interWikiPrefix ) {
		$parser = new WikiLinkParser();

		$parser->registerWikiLinkCleaner( new NamespaceUnlocalizer(
			$sourceLanguage,
			$this->namespaceInfo
		) );

		$parser->registerWikiLinkCleaner( new WikiLinkPrefixer(
			$interWikiPrefix,
			$this->parser
		) );

		return $parser;
	}

}
