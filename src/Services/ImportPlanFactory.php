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

	public function __construct(
		private readonly Config $config,
		private readonly WikiLinkParserFactory $wikiLinkParserFactory,
		private readonly RestrictionStore $restrictionStore,
		private readonly HttpRequestExecutor $httpRequestExecutor,
		private readonly SourceSiteLocator $sourceSiteLocator,
		private readonly DuplicateFileRevisionChecker $duplicateFileRevisionChecker,
		private readonly UploadBaseFactory $uploadBaseFactory,
	) {
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
