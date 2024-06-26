<?php

namespace FileImporter\Remote\MediaWiki;

use FileImporter\Data\SourceUrl;
use FileImporter\Exceptions\HttpRequestException;
use FileImporter\Exceptions\ImportException;
use FileImporter\Exceptions\LocalizedImportException;
use FileImporter\Services\Http\HttpRequestExecutor;
use MediaWiki\Config\Config;
use MediaWiki\MainConfigNames;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\SlotRecord;

/**
 * @license GPL-2.0-or-later
 */
class CommonsHelperConfigRetriever {

	private Config $mainConfig;
	private HttpRequestExecutor $httpRequestExecutor;
	private string $configServer;
	private string $configBasePageName;

	/** @var string|null */
	private $configWikitext = null;
	/** @var string|null */
	private $configWikiUrl = null;

	/**
	 * @param HttpRequestExecutor $httpRequestExecutor
	 * @param string $configServer Full domain including schema, e.g. "https://www.mediawiki.org"
	 * @param string $configBasePageName Base page name, e.g. "Extension:FileImporter/Data/"
	 */
	public function __construct(
		HttpRequestExecutor $httpRequestExecutor,
		string $configServer,
		string $configBasePageName
	) {
		// TODO: Inject?
		$this->mainConfig = MediaWikiServices::getInstance()->getMainConfig();

		$this->httpRequestExecutor = $httpRequestExecutor;
		$this->configServer = $configServer;
		$this->configBasePageName = $configBasePageName;
	}

	/**
	 * @return bool True if a config was found
	 * @throws ImportException e.g. when the config page doesn't exist
	 */
	public function retrieveConfiguration( SourceUrl $sourceUrl ): bool {
		$response = $this->sendApiRequest( $sourceUrl );

		if ( count( $response['query']['pages'] ?? [] ) !== 1 ) {
			return false;
		}

		$currPage = end( $response['query']['pages'] );

		if ( array_key_exists( 'missing', $currPage ) ) {
			return false;
		}

		if ( array_key_exists( 'revisions', $currPage ) ) {
			$latestRevision = end( $currPage['revisions'] );
			if ( array_key_exists( 'slots', $latestRevision ) &&
				array_key_exists( SlotRecord::MAIN, $latestRevision['slots'] ) &&
				array_key_exists( 'content', $latestRevision['slots'][SlotRecord::MAIN] )
			) {
				$this->configWikiUrl = $this->buildCommonsHelperConfigUrl( $sourceUrl );
				$this->configWikitext = $latestRevision['slots'][SlotRecord::MAIN]['content'];
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

	private function buildCommonsHelperConfigUrl( SourceUrl $sourceUrl ): string {
		$title = $this->getQueryParamTitle( $sourceUrl );

		// We assume the wiki holding the config pages uses the same configuration.
		$articlePath = str_replace( '$1', $title, $this->mainConfig->get( MainConfigNames::ArticlePath ) );

		return $this->configServer . $articlePath;
	}

	/**
	 * @return array[]
	 * @throws ImportException when the request failed
	 */
	private function sendApiRequest( SourceUrl $sourceUrl ): array {
		// We assume the wiki holding the config pages uses the same configuration.
		$scriptPath = $this->mainConfig->get( MainConfigNames::ScriptPath );
		$apiUrl = $this->configServer . $scriptPath . '/api.php';
		$apiParameters = [
			'action' => 'query',
			'errorformat' => 'plaintext',
			'format' => 'json',
			'titles' => $this->getQueryParamTitle( $sourceUrl ),
			'prop' => 'revisions',
			'formatversion' => 2,
			'rvprop' => 'content',
			'rvlimit' => 1,
			'rvslots' => SlotRecord::MAIN,
			'rvdir' => 'older'
		];

		try {
			$request = $this->httpRequestExecutor->execute( $apiUrl, $apiParameters );
		} catch ( HttpRequestException $e ) {
			throw new LocalizedImportException( [ 'fileimporter-api-failedtogetinfo',
				$apiUrl ], $e );
		}

		return json_decode( $request->getContent(), true );
	}

	private function getQueryParamTitle( SourceUrl $sourceUrl ): string {
		$domain = $this->getHostWithoutTopLevelDomain( $sourceUrl );

		if ( ctype_alpha( $domain ) ) {
			// Default to "www.mediawiki", even when the source URL was "https://mediawiki.org/…"
			$domain = 'www.' . $domain;
		}

		return str_replace( ' ', '_', $this->configBasePageName ) . $domain;
	}

	/**
	 * @return string Full host with all subdomains, but without the top-level domain (if a
	 *  top-level domain was given), e.g. "en.wikipedia".
	 */
	private function getHostWithoutTopLevelDomain( SourceUrl $sourceUrl ): string {
		$domain = $sourceUrl->getHost();

		// Reuse the original configuration pages for test imports from the Beta cluster
		$domain = str_replace( '.beta.wmflabs.org', '.org', $domain );

		return preg_replace( '/\.\w+$/', '', $domain );
	}

}
