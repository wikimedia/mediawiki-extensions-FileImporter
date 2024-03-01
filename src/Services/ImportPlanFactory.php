<?php

namespace FileImporter\Services;

use FileImporter\Data\ImportDetails;
use FileImporter\Data\ImportPlan;
use FileImporter\Data\ImportRequest;
use FileImporter\Exceptions\ImportException;
use FileImporter\Remote\MediaWiki\CommonsHelperConfigRetriever;
use FileImporter\Services\UploadBase\UploadBaseFactory;
use FileImporter\Services\Wikitext\WikiLinkParserFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\User\User;
use RequestContext;

/**
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class ImportPlanFactory {

	private SourceSiteLocator $sourceSiteLocator;
	private DuplicateFileRevisionChecker $duplicateFileRevisionChecker;
	private UploadBaseFactory $uploadBaseFactory;

	public function __construct(
		SourceSiteLocator $sourceSiteLocator,
		DuplicateFileRevisionChecker $duplicateFileRevisionChecker,
		UploadBaseFactory $uploadBaseFactory
	) {
		$this->sourceSiteLocator = $sourceSiteLocator;
		$this->duplicateFileRevisionChecker = $duplicateFileRevisionChecker;
		$this->uploadBaseFactory = $uploadBaseFactory;
	}

	/**
	 * @return ImportPlan A valid ImportPlan
	 * @throws ImportException
	 */
	public function newPlan( ImportRequest $importRequest, ImportDetails $importDetails, User $user ) {
		$services = MediaWikiServices::getInstance();
		$context = RequestContext::getMain();
		$config = $services->getMainConfig();
		$commonsHelperServer = $config->get( 'FileImporterCommonsHelperServer' );

		if ( $commonsHelperServer ) {
			$commonsHelperConfigRetriever = new CommonsHelperConfigRetriever(
				$services->getService( 'FileImporterHttpRequestExecutor' ),
				$commonsHelperServer,
				$config->get( 'FileImporterCommonsHelperBasePageName' )
			);
			$commonsHelperHelpPage = $config->get( 'FileImporterCommonsHelperHelpPage' ) ?:
				$commonsHelperServer;
		}

		$sourceSite = $this->sourceSiteLocator->getSourceSite( $importDetails->getSourceUrl() );
		$interWikiPrefix = $sourceSite->getLinkPrefix( $importDetails->getSourceUrl() );
		$importPlan = new ImportPlan( $importRequest, $importDetails, $config, $context, $interWikiPrefix );

		$planValidator = new ImportPlanValidator(
			$this->duplicateFileRevisionChecker,
			$sourceSite->getImportTitleChecker(),
			$this->uploadBaseFactory,
			$commonsHelperConfigRetriever ?? null,
			$commonsHelperHelpPage ?? null,
			new WikiLinkParserFactory(),
			$services->getRestrictionStore()
		);
		$planValidator->validate( $importPlan, $user );

		return $importPlan;
	}

}
