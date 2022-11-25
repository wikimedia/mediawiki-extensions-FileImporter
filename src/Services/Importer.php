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
use Liuggio\StatsdClient\Factory\StatsdDataFactoryInterface;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Permissions\Authority;
use MediaWiki\Permissions\RestrictionStore;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityLookup;
use NullStatsdDataFactory;
use OldRevisionImporter;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;
use StatusValue;
use Title;
use UploadRevisionImporter;
use User;
use WikiPage;

/**
 * Performs an import of a file to the local wiki based on an ImportPlan object for a given User.
 *
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class Importer {

	private const ERROR_OPERATION_COMMIT = 'operationCommit';
	private const ERROR_OPERATION_PREPARE = 'operationPrepare';
	private const ERROR_OPERATION_VALIDATE = 'operationValidate';
	private const ERROR_NO_NEW_PAGE = 'noPageCreated';
	private const ERROR_FAILED_POST_IMPORT_EDIT = 'failedPostImportEdit';

	/** @var WikiPageFactory */
	private $wikiPageFactory;
	/** @var WikiRevisionFactory */
	private $wikiRevisionFactory;
	/** @var NullRevisionCreator */
	private $nullRevisionCreator;
	/** @var UserIdentityLookup */
	private $userLookup;
	/** @var HttpRequestExecutor */
	private $httpRequestExecutor;
	/** @var UploadBaseFactory */
	private $uploadBaseFactory;
	/** @var OldRevisionImporter */
	private $oldRevisionImporter;
	/** @var UploadRevisionImporter */
	private $uploadRevisionImporter;
	/** @var FileTextRevisionValidator */
	private $textRevisionValidator;
	/** @var RestrictionStore */
	private $restrictionStore;
	/** @var LoggerInterface */
	private $logger;
	/** @var StatsdDataFactoryInterface */
	private $stats;

	/**
	 * @param WikiPageFactory $wikiPageFactory
	 * @param WikiRevisionFactory $wikiRevisionFactory
	 * @param NullRevisionCreator $nullRevisionCreator
	 * @param UserIdentityLookup $userLookup
	 * @param HttpRequestExecutor $httpRequestExecutor
	 * @param UploadBaseFactory $uploadBaseFactory
	 * @param OldRevisionImporter $oldRevisionImporter
	 * @param UploadRevisionImporter $uploadRevisionImporter
	 * @param FileTextRevisionValidator $textRevisionValidator
	 * @param RestrictionStore $restrictionStore
	 * @param LoggerInterface|null $logger
	 * @param StatsdDataFactoryInterface|null $statsdDataFactory
	 */
	public function __construct(
		WikiPageFactory $wikiPageFactory,
		WikiRevisionFactory $wikiRevisionFactory,
		NullRevisionCreator $nullRevisionCreator,
		UserIdentityLookup $userLookup,
		HttpRequestExecutor $httpRequestExecutor,
		UploadBaseFactory $uploadBaseFactory,
		OldRevisionImporter $oldRevisionImporter,
		UploadRevisionImporter $uploadRevisionImporter,
		FileTextRevisionValidator $textRevisionValidator,
		RestrictionStore $restrictionStore,
		LoggerInterface $logger = null,
		StatsdDataFactoryInterface $statsdDataFactory = null
	) {
		$this->wikiPageFactory = $wikiPageFactory;
		$this->wikiRevisionFactory = $wikiRevisionFactory;
		$this->nullRevisionCreator = $nullRevisionCreator;
		$this->userLookup = $userLookup;
		$this->httpRequestExecutor = $httpRequestExecutor;
		$this->uploadBaseFactory = $uploadBaseFactory;
		$this->oldRevisionImporter = $oldRevisionImporter;
		$this->uploadRevisionImporter = $uploadRevisionImporter;
		$this->textRevisionValidator = $textRevisionValidator;
		$this->restrictionStore = $restrictionStore;
		$this->logger = $logger ?: new NullLogger();
		$this->stats = $statsdDataFactory ?: new NullStatsdDataFactory();
	}

	/**
	 * @param User $user user to use for the import
	 * @param ImportPlan $importPlan A valid ImportPlan object.
	 *
	 * @throws ImportException|RuntimeException
	 */
	public function import( User $user, ImportPlan $importPlan ) {
		$this->wikiRevisionFactory->setInterWikiPrefix( $importPlan->getInterWikiPrefix() );

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
		$this->stats->timing(
			'FileImporter.import.timing.buildOperations',
			( microtime( true ) - $operationBuildingStart ) * 1000
		);

		$operationPrepareStart = microtime( true );
		$this->prepareImportOperations( $importOperations );
		$this->stats->timing(
			'FileImporter.import.timing.prepareOperations',
			( microtime( true ) - $operationPrepareStart ) * 1000
		);

		$operationValidateStart = microtime( true );
		$validationStatus->merge( $importOperations->validate() );
		$this->validateImportOperations( $validationStatus, $importPlan );
		$this->stats->timing(
			'FileImporter.import.timing.validateOperations',
			( microtime( true ) - $operationValidateStart ) * 1000
		);

		$operationCommitStart = microtime( true );
		$this->commitImportOperations( $importOperations );
		$this->stats->timing(
			'FileImporter.import.timing.commitOperations',
			( microtime( true ) - $operationCommitStart ) * 1000
		);

		// TODO the below should be an ImportOperation
		$miscActionsStart = microtime( true );
		$page = $this->getPageFromImportPlan( $importPlan );
		$this->createPostImportNullRevision( $importPlan, $user );
		$this->createPostImportEdit( $importPlan, $page, $user );
		$this->stats->timing(
			'FileImporter.import.timing.miscActions',
			( microtime( true ) - $miscActionsStart ) * 1000
		);

		// TODO do we need to call WikiImporter::finishImportPage??
		// TODO factor logic in WikiImporter::finishImportPage out so we can call it

		$this->stats->timing(
			'FileImporter.import.timing.wholeImport',
			( microtime( true ) - $importStart ) * 1000
		);
	}

	/**
	 * @param User $user
	 * @param Title $plannedTitle
	 * @param ImportDetails $importDetails
	 *
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
			$this->stats->gauge(
				'FileImporter.import.details.individualFileSizes',
				$fileRevision->getField( 'size' )
			);
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

		$this->stats->gauge( 'FileImporter.import.details.textRevisions', count( $textRevisions ) );
		$this->stats->gauge( 'FileImporter.import.details.fileRevisions', count( $fileRevisions ) );
		$this->stats->gauge( 'FileImporter.import.details.totalFileSizes', $totalFileSizes );

		return $importOperations;
	}

	/**
	 * @param ImportOperations $importOperations
	 */
	private function prepareImportOperations( ImportOperations $importOperations ): void {
		if ( !$importOperations->prepare()->isOK() ) {
			$this->logger->error( __METHOD__ . 'Failed to prepare operations.' );
			throw new ImportException( 'Failed to prepare operations.',
				self::ERROR_OPERATION_PREPARE );
		}
	}

	/**
	 * @param StatusValue $status
	 * @param ImportPlan $importPlan
	 */
	private function validateImportOperations( StatusValue $status, ImportPlan $importPlan ): void {
		if ( !$status->isGood() ) {
			/** @var \IApiMessage[] $newAbuseFilterWarnings */
			$newAbuseFilterWarnings = [];

			foreach ( $status->getErrors() as $error ) {
				$message = $error['message'];

				if ( is_string( $message ) && $error['params'] ) {
					// Can be replaced with [ $string, ...$array ] in PHP 7.4
					$message = array_merge( [ $message ], $error['params'] );
				}

				if ( !( $message instanceof \IApiMessage ) ) {
					throw new LocalizedImportException( $message );
				}

				$data = $message->getApiData()['abusefilter'] ?? null;
				if ( !$data ||
					!in_array( 'warn', $data['actions'] ) ||
					in_array( 'disallow', $data['actions'] )
				) {
					throw new LocalizedImportException( $message );
				}

				// Skip AbuseFilter warnings we have seen before
				if ( !in_array( $data['id'], $importPlan->getValidationWarnings() ) ) {
					$importPlan->addValidationWarning( $data['id'] );
					$newAbuseFilterWarnings[] = $message;
				}
			}

			if ( $newAbuseFilterWarnings ) {
				throw new AbuseFilterWarningsException( $newAbuseFilterWarnings );
			}
		}
	}

	/**
	 * @param ImportOperations $importOperations
	 */
	private function commitImportOperations( ImportOperations $importOperations ): void {
		if ( !$importOperations->commit()->isOK() ) {
			$this->logger->error( __METHOD__ . 'Failed to commit operations.' );
			throw new ImportException( 'Failed to commit operations.',
				self::ERROR_OPERATION_COMMIT );
		}
	}

	/**
	 * @param User $user
	 * @param ImportPlan $importPlan
	 * @return StatusValue isOK on success
	 */
	private function validateFileInfoText(
		User $user,
		ImportPlan $importPlan
	): StatusValue {
		$status = $this->textRevisionValidator->validate(
			$importPlan->getTitle(),
			$user,
			new \WikitextContent( $importPlan->getFileInfoText() ),
			$importPlan->getRequest()->getIntendedSummary() ?? '',
			false
		);
		return $status;
	}

	/**
	 * @param ImportPlan $importPlan
	 *
	 * @return WikiPage
	 * @throws ImportException
	 */
	private function getPageFromImportPlan( ImportPlan $importPlan ) {
		/**
		 * T164729 GAID_FOR_UPDATE needed to select for a write
		 */
		$articleIdForUpdate = $importPlan->getTitle()->getArticleID( Title::GAID_FOR_UPDATE );
		// T181391: Read from primary database, as the page has only just been created, and in multi-DB setups
		// replicas will have lag.
		$page = $this->wikiPageFactory->newFromId( $articleIdForUpdate, WikiPage::READ_LATEST );

		if ( !$page ) {
			throw new ImportException(
				'Failed to create import edit with page id: ' . $articleIdForUpdate,
				self::ERROR_NO_NEW_PAGE );
		}

		return $page;
	}

	/**
	 * @param ImportPlan $importPlan
	 * @param UserIdentity $user
	 */
	private function createPostImportNullRevision(
		ImportPlan $importPlan,
		UserIdentity $user
	) {
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
	) {
		// TODO: Replace with $page->newPageUpdater( … )->saveRevision( … )
		$editResult = $page->doUserEditContent(
			new \WikitextContent( $importPlan->getFileInfoText() ),
			$user,
			$importPlan->getRequest()->getIntendedSummary(),
			EDIT_UPDATE,
			false,
			[ 'fileimporter' ]
		);

		if ( !$editResult->isOK() ) {
			$this->logger->error( __METHOD__ . ' Failed to create user edit.' );
			throw new ImportException(
				'Failed to create user edit', self::ERROR_FAILED_POST_IMPORT_EDIT );
		}
	}

}
