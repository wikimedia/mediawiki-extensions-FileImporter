<?php

namespace FileImporter\Remote\MediaWiki;

use FileImporter\Data\SourceUrl;
use FileImporter\Interfaces\SourceUrlChecker;
use Psr\Log\LoggerInterface;

/**
 * This SourceUrlChecker implementation will allow files from mediawiki websites that are contained
 * in the sites table.
 *
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class SiteTableSourceUrlChecker implements SourceUrlChecker {

	private $siteTableSiteLookup;

	/**
	 * @var LoggerInterface
	 */
	private $logger;

	public function __construct(
		SiteTableSiteLookup $siteTableSiteLookup,
		LoggerInterface $logger
	) {
		$this->siteTableSiteLookup = $siteTableSiteLookup;
		$this->logger = $logger;
	}

	public function checkSourceUrl( SourceUrl $sourceUrl ) {
		$site = $this->siteTableSiteLookup->getSite( $sourceUrl->getParsedUrl()['host'] );

		if ( $site === null ) {
			$this->logger->error( __METHOD__ . ' failed site check for URL: ' . $sourceUrl->getUrl() );
			return false;
		}

		$titleString = $this->getTitleFromSourceUrl( $sourceUrl );

		if ( $titleString === null ) {
			$this->logger->error( __METHOD__ . ' failed title check for URL: ' . $sourceUrl->getUrl() );
			return false;
		}

		return true;
	}

	/**
	 * @todo factor out into another object
	 * @param SourceUrl $sourceUrl
	 * @return string|null the string title extracted or null on failure
	 */
	private function getTitleFromSourceUrl( SourceUrl $sourceUrl ) {
		$parsed = $sourceUrl->getParsedUrl();
		$title = null;
		$hasQueryAndTitle = null;

		if ( array_key_exists( 'query', $parsed ) ) {
			parse_str( $parsed['query'], $bits );
			$hasQueryAndTitle = array_key_exists( 'title', $bits );
			if ( $hasQueryAndTitle && strlen( $bits['title'] ) > 0 ) {
				$title = $bits['title'];
			}
		}

		if ( !$hasQueryAndTitle && array_key_exists( 'path', $parsed ) ) {
			$bits = explode( '/', $parsed['path'] );
			if ( count( $bits ) >= 2 && !empty( $bits[count( $bits ) - 1] ) ) {
				$title = array_pop( $bits );
			}
		}

		return $title;
	}

}
