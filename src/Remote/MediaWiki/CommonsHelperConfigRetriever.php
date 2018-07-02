<?php

namespace FileImporter\Remote\MediaWiki;

use FileImporter\Data\SourceUrl;
use FileImporter\Exceptions\HttpRequestException;
use FileImporter\Exceptions\LocalizedImportException;
use FileImporter\Services\Http\HttpRequestExecutor;
use Message;

/**
 * @license GPL-2.0-or-later
 */
class CommonsHelperConfigRetriever {

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
	 * @var SourceUrl
	 */
	private $sourceUrl;

	/**
	 * @var string|null
	 */
	private $configWikiText = null;

	/**
	 * @var string|null
	 */
	private $configWikiUrl = null;

	/**
	 * @param HttpRequestExecutor $httpRequestExecutor
	 * @param string $configServer Full domain including schema, e.g. "https://www.mediawiki.org"
	 * @param string $configBasePageName Base page name, e.g. "Extension:FileImporter/Data/"
	 * @param SourceUrl $sourceUrl
	 */
	public function __construct(
		HttpRequestExecutor $httpRequestExecutor,
		$configServer,
		$configBasePageName,
		SourceUrl $sourceUrl
	) {
		$this->httpRequestExecutor = $httpRequestExecutor;
		$this->configServer = $configServer;
		$this->configBasePageName = $configBasePageName;
		$this->sourceUrl = $sourceUrl;
	}

	/**
	 * @throws LocalizedImportException
	 * @return bool True if a config was found
	 */
	public function retrieveConfiguration() {
		$request = $this->buildAPIRequest( $this->sourceUrl );
		$response = $this->sendAPIRequest( $request );

		if ( count( $response['query']['pages'] ) !== 1 ) {
			return false;
		}

		$currPage = array_pop( $response['query']['pages'] );

		if ( array_key_exists( 'missing', $currPage ) ) {
			return false;
		}

		if ( array_key_exists( 'revisions', $currPage ) ) {
			$latestRevision = array_pop( $currPage['revisions'] );
			if ( array_key_exists( 'content', $latestRevision ) ) {
				$this->configWikiUrl = $this->buildCommonsHelperConfigUrl( $this->sourceUrl );
				$this->configWikiText = $latestRevision['content'];
				return true;
			}
		}

		throw new LocalizedImportException(
			new Message( 'fileimporter-commonshelper-retrieval-failed' )
		);
	}

	/**
	 * @return string|null
	 */
	public function getConfigWikiText() {
		return $this->configWikiText;
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
		return $this->configServer . '/wiki/' . $this->getQueryParamTitle( $sourceUrl );
	}

	/**
	 * @param string $requestUrl
	 *
	 * @return array
	 */
	private function sendAPIRequest( $requestUrl ) {
		try {
			$imageInfoRequest = $this->httpRequestExecutor->execute( $requestUrl );
		} catch ( HttpRequestException $e ) {
			throw new LocalizedImportException(
				new Message( 'fileimporter-api-failedtogetinfo', [ $requestUrl ] )
			);
		}
		$requestData = json_decode( $imageInfoRequest->getContent(), true );
		return $requestData;
	}

	/**
	 * @param SourceUrl $sourceUrl
	 *
	 * @return string
	 */
	private function buildAPIRequest( SourceUrl $sourceUrl ) {
		return $this->configServer . '/w/api.php?' .
			$this->buildAPIQueryParams( $this->getQueryParamTitle( $sourceUrl ) );
	}

	/**
	 * @param string $title
	 *
	 * @return string
	 */
	private function buildAPIQueryParams( $title ) {
		return http_build_query( [
			'action' => 'query',
			'format' => 'json',
			'titles' => $title,
			'prop' => 'revisions',
			'formatversion' => 2,
			'rvprop' => 'content',
			'rvlimit' => 1,
			'rvdir' => 'older'
		] );
	}

	/**
	 * @param SourceUrl $sourceUrl
	 *
	 * @return string
	 */
	private function getQueryParamTitle( SourceUrl $sourceUrl ) {
		$domain = $this->getHostWithoutTopLevelDomain( $sourceUrl );
		if ( ctype_alpha( $domain ) ) {
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
		// Reuse the original CommonsHelper2 config files for test imports from the Beta cluster
		$domain = str_replace( '.beta.wmflabs.org', '.org', $domain );
		return preg_replace( '/\.\w+$/', '', $domain );
	}

}
