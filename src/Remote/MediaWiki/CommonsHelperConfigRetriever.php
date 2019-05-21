<?php

namespace FileImporter\Remote\MediaWiki;

use FileImporter\Data\SourceUrl;
use FileImporter\Exceptions\HttpRequestException;
use FileImporter\Exceptions\LocalizedImportException;
use FileImporter\Services\Http\HttpRequestExecutor;
use MediaWiki\MediaWikiServices;

/**
 * @license GPL-2.0-or-later
 */
class CommonsHelperConfigRetriever {

	/**
	 * @var \Config
	 */
	private $mainConfig;

	/**
	 * @var HttpRequestExecutor
	 */
	private $httpRequestExecutor;

	/**
	 * @var string
	 */
	private $configServer;

	/**
	 * @var string
	 */
	private $configBasePageName;

	/**
	 * @var string|null
	 */
	private $configWikitext = null;

	/**
	 * @var string|null
	 */
	private $configWikiUrl = null;

	/**
	 * @param HttpRequestExecutor $httpRequestExecutor
	 * @param string $configServer Full domain including schema, e.g. "https://www.mediawiki.org"
	 * @param string $configBasePageName Base page name, e.g. "Extension:FileImporter/Data/"
	 */
	public function __construct(
		HttpRequestExecutor $httpRequestExecutor,
		$configServer,
		$configBasePageName
	) {
		// TODO: Inject?
		$this->mainConfig = MediaWikiServices::getInstance()->getMainConfig();

		$this->httpRequestExecutor = $httpRequestExecutor;
		$this->configServer = $configServer;
		$this->configBasePageName = $configBasePageName;
	}

	/**
	 * @param SourceUrl $sourceUrl
	 *
	 * @throws LocalizedImportException
	 * @return bool True if a config was found
	 */
	public function retrieveConfiguration( SourceUrl $sourceUrl ) {
		$request = $this->buildApiRequest( $sourceUrl );
		$response = $this->sendApiRequest( $request );

		if ( !isset( $response['query']['pages'] ) ||
			count( $response['query']['pages'] ) !== 1
		) {
			return false;
		}

		$currPage = end( $response['query']['pages'] );

		if ( array_key_exists( 'missing', $currPage ) ) {
			return false;
		}

		if ( array_key_exists( 'revisions', $currPage ) ) {
			$latestRevision = end( $currPage['revisions'] );
			if ( array_key_exists( 'content', $latestRevision ) ) {
				$this->configWikiUrl = $this->buildCommonsHelperConfigUrl( $sourceUrl );
				$this->configWikitext = $latestRevision['content'];
				return true;
			}
		}

		throw new LocalizedImportException( 'fileimporter-commonshelper-retrieval-failed' );
	}

	/**
	 * @return string|null
	 */
	public function getConfigWikitext() {
		return $this->configWikitext;
	}

	/**
	 * @return string|null
	 */
	public function getConfigWikiUrl() {
		return $this->configWikiUrl;
	}

	/**
	 * @param SourceUrl $sourceUrl
	 *
	 * @return string
	 */
	private function buildCommonsHelperConfigUrl( SourceUrl $sourceUrl ) {
		$title = $this->getQueryParamTitle( $sourceUrl );

		// We assume the wiki holding the config pages uses the same configuration.
		$articlePath = str_replace( '$1', $title, $this->mainConfig->get( 'ArticlePath' ) );

		return $this->configServer . $articlePath;
	}

	/**
	 * @param string $requestUrl
	 *
	 * @return array[]
	 */
	private function sendApiRequest( $requestUrl ) {
		try {
			$imageInfoRequest = $this->httpRequestExecutor->execute( $requestUrl );
		} catch ( HttpRequestException $e ) {
			throw new LocalizedImportException( [ 'fileimporter-api-failedtogetinfo',
				$requestUrl ] );
		}
		$requestData = json_decode( $imageInfoRequest->getContent(), true );
		return $requestData;
	}

	/**
	 * @param SourceUrl $sourceUrl
	 *
	 * @return string
	 */
	private function buildApiRequest( SourceUrl $sourceUrl ) {
		// We assume the wiki holding the config pages uses the same configuration.
		$scriptPath = $this->mainConfig->get( 'ScriptPath' );

		return wfAppendQuery(
			$this->configServer . $scriptPath . '/api.php',
			[
				'action' => 'query',
				'format' => 'json',
				'titles' => $this->getQueryParamTitle( $sourceUrl ),
				'prop' => 'revisions',
				'formatversion' => 2,
				'rvprop' => 'content',
				'rvlimit' => 1,
				'rvdir' => 'older'
			]
		);
	}

	/**
	 * @param SourceUrl $sourceUrl
	 *
	 * @return string
	 */
	private function getQueryParamTitle( SourceUrl $sourceUrl ) {
		$domain = $this->getHostWithoutTopLevelDomain( $sourceUrl );

		if ( ctype_alpha( $domain ) ) {
			// Default to "www.mediawiki", even when the source URL was "https://mediawiki.org/…"
			$domain = 'www.' . $domain;
		}

		return str_replace( ' ', '_', $this->configBasePageName ) . $domain;
	}

	/**
	 * @param SourceUrl $sourceUrl
	 *
	 * @return string Full host with all subdomains, but without the top-level domain (if a
	 *  top-level domain was given), e.g. "en.wikipedia".
	 */
	private function getHostWithoutTopLevelDomain( SourceUrl $sourceUrl ) {
		$domain = $sourceUrl->getHost();

		// Reuse the original configuration pages for test imports from the Beta cluster
		$domain = str_replace( '.beta.wmflabs.org', '.org', $domain );

		return preg_replace( '/\.\w+$/', '', $domain );
	}

}
