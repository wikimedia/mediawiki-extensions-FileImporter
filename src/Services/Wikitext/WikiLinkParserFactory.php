<?php

namespace FileImporter\Services\Wikitext;

use FileImporter\Remote\MediaWiki\MediaWikiSourceUrlParser;
use MediaWiki\Languages\LanguageFactory;
use MediaWiki\MediaWikiServices;
use NamespaceInfo;
use TitleParser;

/**
 * @license GPL-2.0-or-later
 * @author Thiemo Kreuz
 */
class WikiLinkParserFactory {
	use MediaWikiSourceUrlParser;

	/** @var TitleParser */
	private $parser;
	/** @var NamespaceInfo */
	private $namespaceInfo;
	/** @var LanguageFactory */
	private $languageFactory;

	public function __construct() {
		$services = MediaWikiServices::getInstance();
		$this->parser = $services->getTitleParser();
		$this->namespaceInfo = $services->getNamespaceInfo();
		$this->languageFactory = $services->getLanguageFactory();
	}

	/**
	 * @param string|null $languageCode
	 * @param string $interWikiPrefix
	 *
	 * @return WikiLinkParser
	 */
	public function getWikiLinkParser( ?string $languageCode, string $interWikiPrefix ): WikiLinkParser {
		$parser = new WikiLinkParser();

		// Minor performance optimization: skip this step if there is nothing to unlocalize
		if ( $languageCode && $languageCode !== 'en' ) {
			$language = $this->languageFactory->getLanguage( $languageCode );
			$parser->registerWikiLinkCleaner( new NamespaceUnlocalizer(
				new LocalizedMediaWikiNamespaceLookup( $language ),
				$this->namespaceInfo
			) );
		}

		$parser->registerWikiLinkCleaner( new WikiLinkPrefixer(
			$interWikiPrefix,
			$this->parser
		) );

		return $parser;
	}

}
