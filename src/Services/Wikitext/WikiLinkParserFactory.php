<?php

namespace FileImporter\Services\Wikitext;

use FileImporter\Remote\MediaWiki\MediaWikiSourceUrlParser;
use MediaWiki\Languages\LanguageFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\NamespaceInfo;
use MediaWiki\Title\TitleParser;

/**
 * @license GPL-2.0-or-later
 * @author Thiemo Kreuz
 */
class WikiLinkParserFactory {
	use MediaWikiSourceUrlParser;

	private TitleParser $parser;
	private NamespaceInfo $namespaceInfo;
	private LanguageFactory $languageFactory;

	public function __construct() {
		$services = MediaWikiServices::getInstance();
		// FIXME: This needs to be a parser in the context of the *source* wiki
		$this->parser = $services->getTitleParser();
		$this->namespaceInfo = $services->getNamespaceInfo();
		$this->languageFactory = $services->getLanguageFactory();
	}

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
