<?php

namespace FileImporter\Services\Wikitext;

use FileImporter\Remote\MediaWiki\MediaWikiSourceUrlParser;
use MediaWiki\Languages\LanguageFactory;
use MediaWiki\Title\NamespaceInfo;
use MediaWiki\Title\TitleParser;

/**
 * @license GPL-2.0-or-later
 * @author Thiemo Kreuz
 */
class WikiLinkParserFactory {
	use MediaWikiSourceUrlParser;

	public function __construct(
		// FIXME: This needs to be a parser in the context of the *source* wiki
		private readonly TitleParser $titleParser,
		private readonly NamespaceInfo $namespaceInfo,
		private readonly LanguageFactory $languageFactory,
	) {
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
			$this->titleParser
		) );

		return $parser;
	}

}
