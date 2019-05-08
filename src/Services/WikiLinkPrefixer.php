<?php

namespace FileImporter\Services;

use FileImporter\Services\Wikitext\WikiLinkCleaner;
use MalformedTitleException;
use TitleParser;

/**
 * @license GPL-2.0-or-later
 */
class WikiLinkPrefixer implements WikiLinkCleaner {

	/**
	 * @var string
	 */
	private $interWikiPrefix;

	/**
	 * @var TitleParser
	 */
	private $parser;

	/**
	 * @param string $interWikiPrefix E.g. "de" for the German Wikipedia.
	 * @param TitleParser $parser
	 */
	public function __construct( $interWikiPrefix, TitleParser $parser ) {
		$this->interWikiPrefix = $interWikiPrefix;
		$this->parser = $parser;
	}

	/**
	 * @param string $link
	 *
	 * @return string
	 */
	public function process( $link ) {
		if ( $this->interWikiPrefix === '' ) {
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
			|| strcasecmp( $title->getInterwiki(), $this->interWikiPrefix ) === 0
		) {
			return $link;
		}

		return ':' . $this->interWikiPrefix . ':' . ltrim( ltrim( $link ), ':' );
	}

}
