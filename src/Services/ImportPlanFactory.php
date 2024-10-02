<?php

namespace FileImporter\Services;

use FileImporter\Data\ImportDetails;
use FileImporter\Data\ImportPlan;
use FileImporter\Data\ImportRequest;
use FileImporter\Exceptions\ImportException;
use FileImporter\Remote\MediaWiki\CommonsHelperConfigRetriever;
use FileImporter\Services\Http\HttpRequestExecutor;
use FileImporter\Services\UploadBase\UploadBaseFactory;
use FileImporter\Services\Wikitext\WikiLinkParserFactory;
use MediaWiki\Config\Config;
use MediaWiki\Context\RequestContext;
use MediaWiki\Permissions\RestrictionStore;
use MediaWiki\User\User;

/**
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class ImportPlanFactory {

	private Config $config;
	private WikiLinkParserFactory $wikiLinkParserFactory;
	private RestrictionStore $restrictionStore;
	private HttpRequestExecutor $httpRequestExecutor;
	private SourceSiteLocator $sourceSiteLocator;
	private DuplicateFileRevisionChecker $duplicateFileRevisionChecker;
	private UploadBaseFactory $uploadBaseFactory;

	public function __construct(
		Config $config,
		WikiLinkParserFactory $wikiLinkParserFactory,
		RestrictionStore $restrictionStore,
		HttpRequestExecutor $httpRequestExecutor,
		SourceSiteLocator $sourceSiteLocator,
		DuplicateFileRevisionChecker $duplicateFileRevisionChecker,
		UploadBaseFactory $uploadBaseFactory
	) {
		$this->config = $config;
		$this->wikiLinkParserFactory = $wikiLinkParserFactory;
		$this->restrictionStore = $restrictionStore;
		$this->httpRequestExecutor = $httpRequestExecutor;
		$this->sourceSiteLocator = $sourceSiteLocator;
		$this->duplicateFileRevisionChecker = $duplicateFileRevisionChecker;
		$this->uploadBaseFactory = $uploadBaseFactory;
	}

	/**
	 * @return ImportPlan A valid ImportPlan
	 * @throws ImportException
	 */
	public function newPlan( ImportRequest $importRequest, ImportDetails $importDetails, User $user ) {
		$context = RequestContext::getMain();
		$commonsHelperServer = $this->config->get( 'FileImporterCommonsHelperServer' );

		if ( $commonsHelperServer ) {
			$commonsHelperConfigRetriever = new CommonsHelperConfigRetriever(
				$this->httpRequestExecutor,
				$commonsHelperServer,
				$this->config->get( 'FileImporterCommonsHelperBasePageName' )
			);
			$commonsHelperHelpPage = $this->config->get( 'FileImporterCommonsHelperHelpPage' ) ?:
				$commonsHelperServer;
		}

		$sourceSite = $this->sourceSiteLocator->getSourceSite( $importDetails->getSourceUrl() );
		$interWikiPrefix = $sourceSite->getLinkPrefix( $importDetails->getSourceUrl() );
		$importPlan = new ImportPlan( $importRequest, $importDetails, $this->config, $context, $interWikiPrefix );

		$planValidator = new ImportPlanValidator(
			$this->duplicateFileRevisionChecker,
			$sourceSite->getImportTitleChecker(),
			$this->uploadBaseFactory,
			$commonsHelperConfigRetriever ?? null,
			$commonsHelperHelpPage ?? null,
			$this->wikiLinkParserFactory,
			$this->restrictionStore
		);
		$planValidator->validate( $importPlan, $user );

		return $importPlan;
	}

}
