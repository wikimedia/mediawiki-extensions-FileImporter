<?php

namespace FileImporter\Services\Wikitext;

use FileImporter\Data\SourceUrl;
use FileImporter\Exceptions\HttpRequestException;
use FileImporter\Exceptions\LocalizedImportException;
use FileImporter\Remote\MediaWiki\HttpApiLookup;
use FileImporter\Remote\MediaWiki\MediaWikiSourceUrlParser;
use FileImporter\Services\Http\HttpRequestExecutor;
use Interwiki;
use Language;
use MediaWiki\Interwiki\InterwikiLookup;
use MediaWiki\MediaWikiServices;
use NamespaceInfo;
use SiteLookup;
use TitleParser;

/**
 * @license GPL-2.0-or-later
 * @author Thiemo Kreuz
 */
class WikiLinkParserFactory {
	use MediaWikiSourceUrlParser;

	/**
	 * @var HttpApiLookup
	 */
	private $httpApiLookup;

	/**
	 * @var HttpRequestExecutor
	 */
	private $httpRequestExecutor;

	/**
	 * @var TitleParser
	 */
	private $parser;

	/**
	 * @var InterwikiLookup
	 */
	private $interwikiLookup;

	/**
	 * @var SiteLookup
	 */
	private $siteLookup;

	/**
	 * @var NamespaceInfo
	 */
	private $namespaceInfo;

	public function __construct(
		HttpApiLookup $httpApiLookup,
		HttpRequestExecutor $httpRequestExecutor
	) {
		// TODO: Aren't these always used together? Why not bundle them in one service?
		$this->httpApiLookup = $httpApiLookup;
		$this->httpRequestExecutor = $httpRequestExecutor;

		$services = MediaWikiServices::getInstance();
		$this->parser = $services->getTitleParser();
		$this->interwikiLookup = $services->getInterwikiLookup();
		$this->siteLookup = $services->getSiteLookup();
		$this->namespaceInfo = $services->getNamespaceInfo();
	}

	/**
	 * @param SourceUrl $sourceUrl
	 * @param string $interWikiPrefix
	 *
	 * @return WikiLinkParser
	 */
	public function getWikiLinkParser( SourceUrl $sourceUrl, $interWikiPrefix ) {
		$parser = new WikiLinkParser();

		$parser->registerWikiLinkCleaner( new NamespaceUnlocalizer(
			Language::factory( $this->getLanguageCode( $sourceUrl, $interWikiPrefix ) ),
			$this->namespaceInfo
		) );

		$parser->registerWikiLinkCleaner( new WikiLinkPrefixer(
			$interWikiPrefix,
			$this->parser
		) );

		return $parser;
	}

	/**
	 * @param SourceUrl $sourceUrl
	 * @param string $interWikiPrefix
	 *
	 * @return string
	 */
	private function getLanguageCode( SourceUrl $sourceUrl, $interWikiPrefix ) {
		$languageCode = null;

		$interwiki = $this->interwikiLookup->fetch( $interWikiPrefix );
		if ( $interwiki ) {
			$languageCode = $this->getLanguageCodeFromSiteTable( $interwiki );
		}

		if ( !$languageCode ) {
			$languageCode = $this->getLanguageCodeFromApi( $sourceUrl );
		}

		return $languageCode ?: 'en';
	}

	/**
	 * @param Interwiki $interwiki
	 *
	 * @return string|null
	 */
	private function getLanguageCodeFromSiteTable( Interwiki $interwiki ) {
		// TODO: Is this "wiki ID" and the expected "site ID" the same?
		$site = $this->siteLookup->getSite( $interwiki->getWikiID() );
		return $site ? $site->getLanguageCode() : null;
	}

	private function getLanguageCodeFromApi( SourceUrl $sourceUrl ) {
		$apiUrl = wfAppendQuery(
			$this->httpApiLookup->getApiUrl( $sourceUrl ),
			[
				'action' => 'query',
				'format' => 'json',
				'prop' => 'info',
				'titles' => $this->parseTitleFromSourceUrl( $sourceUrl ),
			]
		);

		try {
			$request = $this->httpRequestExecutor->execute( $apiUrl );
		} catch ( HttpRequestException $ex ) {
			// TODO: Is it ok to reuse this message?
			throw new LocalizedImportException( [ 'fileimporter-api-failedtogetinfo', $apiUrl ] );
		}

		$data = json_decode( $request->getContent(), true );
		if ( isset( $data['query']['pages'] ) ) {
			return reset( $data['query']['pages'] )['pagelanguage'];
		}

		return null;
	}

}
