<?php

namespace FileImporter\Services;

use FileImporter\Data\ImportDetails;
use FileImporter\Data\ImportOperations;
use FileImporter\Data\ImportPlan;
use FileImporter\Exceptions\AbuseFilterWarningsException;
use FileImporter\Exceptions\ImportException;
use FileImporter\Exceptions\LocalizedImportException;
use FileImporter\Operations\FileRevisionFromRemoteUrl;
use FileImporter\Operations\TextRevisionFromTextRevision;
use FileImporter\Services\Http\HttpRequestExecutor;
use FileImporter\Services\UploadBase\UploadBaseFactory;
use MediaWiki\Api\IApiMessage;
use MediaWiki\Content\WikitextContent;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\WikiPage;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Permissions\Authority;
use MediaWiki\Permissions\RestrictionStore;
use MediaWiki\Status\Status;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityLookup;
use OldRevisionImporter;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;
use StatusValue;
use UploadRevisionImporter;
use Wikimedia\Message\MessageSpecifier;
use Wikimedia\Rdbms\IDBAccessObject;
use Wikimedia\Stats\StatsFactory;

/**
 * Performs an import of a file to the local wiki based on an ImportPlan object for a given User.
 *
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class Importer {

	private const ERROR_NO_NEW_PAGE = 'noPageCreated';

	private StatsFactory $statsFactory;

	public function __construct(
		private readonly WikiPageFactory $wikiPageFactory,
		private readonly WikiRevisionFactory $wikiRevisionFactory,
		private readonly NullRevisionCreator $nullRevisionCreator,
		private readonly UserIdentityLookup $userLookup,
		private readonly HttpRequestExecutor $httpRequestExecutor,
		private readonly UploadBaseFactory $uploadBaseFactory,
		private readonly OldRevisionImporter $oldRevisionImporter,
		private readonly UploadRevisionImporter $uploadRevisionImporter,
		private readonly FileTextRevisionValidator $textRevisionValidator,
		private readonly RestrictionStore $restrictionStore,
		private readonly LoggerInterface $logger = new NullLogger(),
		?StatsFactory $statsFactory = null,
	) {
		$statsFactory ??= StatsFactory::newNull();
		$this->statsFactory = $statsFactory->withComponent( 'FileImporter' );
	}

	/**
	 * @param User $user user to use for the import
	 * @param ImportPlan $importPlan A valid ImportPlan object.
	 *
	 * @throws ImportException|RuntimeException
	 */
	public function import( User $user, ImportPlan $importPlan ): void {
		$this->wikiRevisionFactory->setInterWikiPrefix( $importPlan->getInterWikiPrefix() );
		$metric = $this->statsFactory->getTiming( 'import_operation_duration_seconds' );

		$importStart = microtime( true );
		$this->logger->info( __METHOD__ . ' started' );

		$validationStatus = $this->validateFileInfoText(
			$user,
			$importPlan
		);

		// TODO the type of ImportOperation created should be decided somewhere

		$operationBuildingStart = microtime( true );
		$importOperations = $this->buildImportOperations(
			$user,
			$importPlan->getTitle(),
			$importPlan->getDetails()
		);
		$metric->setLabel( 'operation', 'build' )
			->copyToStatsdAt( 'FileImporter.import.timing.buildOperations' )
			->observeSeconds( microtime( true ) - $operationBuildingStart );

		$operationPrepareStart = microtime( true );
		$this->prepareImportOperations( $importOperations );
		$metric->setLabel( 'operation', 'prepare' )
			->copyToStatsdAt( 'FileImporter.import.timing.prepareOperations' )
			->observeSeconds( microtime( true ) - $operationPrepareStart );

		$operationValidateStart = microtime( true );
		$validationStatus->merge( $importOperations->validate() );
		$this->validateImportOperations( $validationStatus, $importPlan );
		$metric->setLabel( 'operation', 'validate' )
			->copyToStatsdAt( 'FileImporter.import.timing.validateOperations' )
			->observeSeconds( microtime( true ) - $operationValidateStart );

		$operationCommitStart = microtime( true );
		$this->commitImportOperations( $importOperations );
		$metric->setLabel( 'operation', 'commit' )
			->copyToStatsdAt( 'FileImporter.import.timing.commitOperations' )
			->observeSeconds( microtime( true ) - $operationCommitStart );

		// TODO the below should be an ImportOperation
		$miscActionsStart = microtime( true );
		$page = $this->getPageFromImportPlan( $importPlan );
		$this->createPostImportNullRevision( $importPlan, $user );
		$this->createPostImportEdit( $importPlan, $page, $user );
		$metric->setLabel( 'operation', 'misc' )
			->copyToStatsdAt( 'FileImporter.import.timing.miscActions' )
			->observeSeconds( microtime( true ) - $miscActionsStart );

		// TODO do we need to call WikiImporter::finishImportPage??
		// TODO factor logic in WikiImporter::finishImportPage out so we can call it

		$this->statsFactory->getTiming( 'import_duration_seconds' )
			->copyToStatsdAt( 'FileImporter.import.timing.wholeImport' )
			->observeSeconds( microtime( true ) - $importStart );
	}

	/**
	 * @return ImportOperations
	 */
	private function buildImportOperations(
		User $user,
		Title $plannedTitle,
		ImportDetails $importDetails
	) {
		$textRevisions = $importDetails->getTextRevisions()->toArray();
		$fileRevisions = $importDetails->getFileRevisions()->toArray();
		$importOperations = new ImportOperations();

		/**
		 * Text revisions should be added first. See T147451.
		 * This ensures that the page entry is created and if something fails it can thus be deleted.
		 */
		foreach ( $textRevisions as $textRevision ) {
			$importOperations->add( new TextRevisionFromTextRevision(
				$plannedTitle,
				$user,
				$textRevision,
				$this->wikiRevisionFactory,
				$this->oldRevisionImporter,
				$this->textRevisionValidator,
				$this->restrictionStore,
				$this->logger
			) );
		}

		$totalFileSizes = 0;
		$initialTextRevision = $textRevisions[0] ?? null;

		foreach ( $fileRevisions as $fileRevision ) {
			$totalFileSizes += $fileRevision->getField( 'size' );
			$importOperations->add( new FileRevisionFromRemoteUrl(
				$plannedTitle,
				$user,
				$fileRevision,
				$initialTextRevision,
				$this->userLookup,
				$this->httpRequestExecutor,
				$this->wikiRevisionFactory,
				$this->uploadBaseFactory,
				$this->uploadRevisionImporter,
				$this->restrictionStore,
				$this->logger
			) );

			// only include the initial text revision in the first upload
			$initialTextRevision = null;
		}
		$this->statsFactory->getGauge( 'import_details_textRevisions' )
			->copyToStatsdAt( 'FileImporter.import.details.textRevisions' )
			->set( count( $textRevisions ) );
		$this->statsFactory->getGauge( 'import_details_fileRevisions' )
			->copyToStatsdAt( 'FileImporter.import.details.fileRevisions' )
			->set( count( $fileRevisions ) );

		$this->statsFactory->getGauge( 'import_details_totalFileSizes_bytes' )
			->copyToStatsdAt( 'FileImporter.import.details.totalFileSizes' )
			->set( $totalFileSizes );

		return $importOperations;
	}

	private function prepareImportOperations( ImportOperations $importOperations ): void {
		$status = $importOperations->prepare();
		if ( !$status->isOK() ) {
			$this->logger->error( __METHOD__ . ' Failed to prepare operations.', [
				'status' => $status->__toString(),
			] );
			throw new LocalizedImportException( Status::wrap( $status )->getMessage() );
		}
	}

	private function validateImportOperations( StatusValue $status, ImportPlan $importPlan ): void {
		if ( !$status->isGood() ) {
			/** @var MessageSpecifier[] $newAbuseFilterWarnings */
			$newAbuseFilterWarnings = [];

			foreach ( $status->getMessages() as $msg ) {
				if ( !( $msg instanceof IApiMessage ) ) {
					// Unexpected errors bubble up and surface in SpecialImportFile::doImport
					throw new LocalizedImportException( $msg );
				}

				$data = $msg->getApiData()['abusefilter'] ?? null;
				if ( !$data ||
					!in_array( 'warn', $data['actions'] ) ||
					in_array( 'disallow', $data['actions'] )
				) {
					throw new LocalizedImportException( $msg );
				}

				// Skip AbuseFilter warnings we have seen before
				if ( !in_array( $data['id'], $importPlan->getValidationWarnings() ) ) {
					// @phan-suppress-next-line PhanTypeMismatchArgument
					$importPlan->addValidationWarning( $data['id'] );
					$newAbuseFilterWarnings[] = $msg;
				}
			}

			if ( $newAbuseFilterWarnings ) {
				throw new AbuseFilterWarningsException( $newAbuseFilterWarnings );
			}
		}
	}

	private function commitImportOperations( ImportOperations $importOperations ): void {
		$status = $importOperations->commit();
		if ( !$status->isOK() ) {
			$this->logger->error( __METHOD__ . ' Failed to commit operations.', [
				'status' => $status->__toString(),
			] );
			throw new LocalizedImportException( Status::wrap( $status )->getMessage() );
		}
	}

	/**
	 * @return StatusValue isOK on success
	 */
	private function validateFileInfoText(
		User $user,
		ImportPlan $importPlan
	): StatusValue {
		$status = $this->textRevisionValidator->validate(
			$importPlan->getTitle(),
			$user,
			new WikitextContent( $importPlan->getFileInfoText() ),
			$importPlan->getRequest()->getIntendedSummary() ?? '',
			false
		);
		return $status;
	}

	/**
	 * @return WikiPage
	 * @throws ImportException
	 */
	private function getPageFromImportPlan( ImportPlan $importPlan ) {
		// T164729: READ_LATEST needed to select for a write
		$articleIdForUpdate = $importPlan->getTitle()->getArticleID( IDBAccessObject::READ_LATEST );
		// T181391: Read from primary database, as the page has only just been created, and in multi-DB setups
		// replicas will have lag.
		$page = $this->wikiPageFactory->newFromId( $articleIdForUpdate, IDBAccessObject::READ_LATEST );

		if ( !$page ) {
			throw new ImportException(
				'Failed to create import edit with page id: ' . $articleIdForUpdate,
				self::ERROR_NO_NEW_PAGE );
		}

		return $page;
	}

	private function createPostImportNullRevision(
		ImportPlan $importPlan,
		UserIdentity $user
	): void {
		$config = MediaWikiServices::getInstance()->getMainConfig();
		$summary = wfMsgReplaceArgs(
			$config->get( 'FileImporterCommentForPostImportRevision' ),
			[ $importPlan->getRequest()->getUrl() ]
		);

		try {
			$this->nullRevisionCreator->createForLinkTarget(
				$importPlan->getTitle(),
				$importPlan->getDetails()->getFileRevisions()->getLatest(),
				$user,
				$summary
			);
		} catch ( RuntimeException $ex ) {
			$this->logger->error( __METHOD__ . ' Failed to create import revision.' );
			throw $ex;
		}
	}

	/**
	 * @param ImportPlan $importPlan
	 * @param WikiPage $page
	 * @param Authority $user
	 */
	private function createPostImportEdit(
		ImportPlan $importPlan,
		WikiPage $page,
		Authority $user
	): void {
		// TODO: Replace with $page->newPageUpdater( … )->saveRevision( … )
		$editResult = $page->doUserEditContent(
			new WikitextContent( $importPlan->getFileInfoText() ),
			$user,
			$importPlan->getRequest()->getIntendedSummary(),
			EDIT_UPDATE,
			false,
			[ 'fileimporter' ]
		);

		if ( !$editResult->isOK() ) {
			$this->logger->error( __METHOD__ . ' Failed to create user edit.', [
				'status' => $editResult->__toString(),
			] );
			throw new LocalizedImportException( Status::wrap( $editResult )->getMessage() );
		}
	}

}
