<?php

namespace FileImporter\Services\Wikitext;

use MediaWiki\Title\MalformedTitleException;
use MediaWiki\Title\TitleParser;

/**
 * A small parser that adds an interwiki prefix to all links it can't understand. Incoming links are
 * expected to have canonical English namespace names, or namespace names in the target wikis
 * content language.
 *
 * This class uses MediaWiki's TitleParser to extract known interwiki prefixes and namespaces from
 * the link, but doesn't use it to do any normalization.
 *
 * @license GPL-2.0-or-later
 */
class WikiLinkPrefixer implements WikiLinkCleaner {

	private string $interWikiPrefix;
	private TitleParser $parser;

	/**
	 * @param string $interWikiPrefix E.g. "de" for the German Wikipedia.
	 * @param TitleParser $parser
	 */
	public function __construct( string $interWikiPrefix, TitleParser $parser ) {
		$this->interWikiPrefix = $interWikiPrefix;
		$this->parser = $parser;
	}

	public function process( string $link ): string {
		if ( $this->interWikiPrefix === ''
			// Bail out early if the prefix is already there; the extra + avoid backtracking
			|| preg_match( '{^\h*+:?\h*+' . preg_quote( $this->interWikiPrefix ) . '\h*+:}iu', $link )
		) {
			return $link;
		}

		try {
			$title = $this->parser->parseTitle( $link );
		} catch ( MalformedTitleException $ex ) {
			return $link;
		}

		if ( $title->inNamespace( NS_CATEGORY )
			// The syntax for thumbnail images would break with a prefix
			|| $title->inNamespace( NS_FILE )
			|| $title->inNamespace( NS_MEDIA )
			|| strcasecmp( $title->getInterwiki(), $this->interWikiPrefix ) === 0
		) {
			return $link;
		}

		return ':' . $this->interWikiPrefix . ':' . ltrim( ltrim( $link ), ':' );
	}

}
