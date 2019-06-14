<?php

namespace FileImporter\Services;

use Exception;
use FileImporter\Data\ImportDetails;
use FileImporter\Data\ImportOperations;
use FileImporter\Data\ImportPlan;
use Liuggio\StatsdClient\Factory\StatsdDataFactoryInterface;
use MediaWiki\MediaWikiServices;
use FileImporter\Exceptions\ImportException;
use FileImporter\Operations\FileRevisionFromRemoteUrl;
use FileImporter\Operations\TextRevisionFromTextRevision;
use FileImporter\Services\Http\HttpRequestExecutor;
use FileImporter\Services\UploadBase\UploadBaseFactory;
use OldRevisionImporter;
use Psr\Log\LoggerInterface;
use RuntimeException;
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

	const ERROR_OPERATION_COMMIT = 'operationCommit';
	const ERROR_OPERATION_PREPARE = 'operationPrepare';
	const ERROR_OPERATION_VALIDATE = 'operationValidate';
	const ERROR_NO_NEW_PAGE = 'noPageCreated';
	const ERROR_FAILED_POST_IMPORT_EDIT = 'failedPostImportEdit';

	/**
	 * @var WikiPageFactory
	 */
	private $wikiPageFactory;

	/**
	 * @var WikiRevisionFactory
	 */
	private $wikiRevisionFactory;

	/**
	 * @var NullRevisionCreator
	 */
	private $nullRevisionCreator;

	/**
	 * @var HttpRequestExecutor
	 */
	private $httpRequestExecutor;

	/**
	 * @var UploadBaseFactory
	 */
	private $uploadBaseFactory;

	/**
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * @var OldRevisionImporter
	 */
	private $oldRevisionImporter;

	/**
	 * @var UploadRevisionImporter
	 */
	private $uploadRevisionImporter;

	/**
	 * @var FileTextRevisionValidator
	 */
	private $textRevisionValidator;

	/**
	 * @var StatsdDataFactoryInterface
	 */
	private $stats;

	public function __construct(
		WikiPageFactory $wikiPageFactory,
		WikiRevisionFactory $wikiRevisionFactory,
		NullRevisionCreator $nullRevisionCreator,
		HttpRequestExecutor $httpRequestExecutor,
		UploadBaseFactory $uploadBaseFactory,
		OldRevisionImporter $oldRevisionImporter,
		UploadRevisionImporter $uploadRevisionImporter,
		FileTextRevisionValidator $textRevisionValidator,
		StatsdDataFactoryInterface $statsdDataFactory,
		LoggerInterface $logger
	) {
		$this->wikiPageFactory = $wikiPageFactory;
		$this->wikiRevisionFactory = $wikiRevisionFactory;
		$this->nullRevisionCreator = $nullRevisionCreator;
		$this->httpRequestExecutor = $httpRequestExecutor;
		$this->uploadBaseFactory = $uploadBaseFactory;
		$this->oldRevisionImporter = $oldRevisionImporter;
		$this->uploadRevisionImporter = $uploadRevisionImporter;
		$this->textRevisionValidator = $textRevisionValidator;
		$this->stats = $statsdDataFactory;
		$this->logger = $logger;
	}

	/**
	 * @param User $user user to use for the import
	 * @param ImportPlan $importPlan A valid ImportPlan object.
	 *
	 * @return bool success
	 * @throws ImportException|RuntimeException
	 */
	public function import(
		User $user,
		ImportPlan $importPlan
	) {
		try {
			$result = $this->importInternal( $user, $importPlan );
			$this->stats->increment( 'FileImporter.import.result.success' );
			return $result;
		} catch ( Exception $e ) {
			// Catch all exception and re throw them after counting them
			$this->stats->increment( 'FileImporter.import.result.exception' );
			throw $e;
		}
	}

	/**
	 * @param User $user user to use for the import
	 * @param ImportPlan $importPlan A valid ImportPlan object.
	 *
	 * @return bool success
	 * @throws ImportException|RuntimeException
	 */
	private function importInternal(
		User $user,
		ImportPlan $importPlan
	) {
		$this->wikiRevisionFactory->setInterWikiPrefix( $importPlan->getInterWikiPrefix() );

		$importStart = microtime( true );
		$this->logger->info( __METHOD__ . ' started' );

		$this->validateFileInfoText(
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
		$this->validateImportOperations( $importOperations );
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

		return true;
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
				$this->httpRequestExecutor,
				$this->wikiRevisionFactory,
				$this->uploadBaseFactory,
				$this->uploadRevisionImporter,
				$this->logger
			) );

			// only include the initial text revision in the first upload
			$initialTextRevision = null;
		}

		$this->stats->gauge( 'FileImporter.import.details.textRevisions', count( $textRevisions ) );
		$this->stats->gauge( 'FileImporter.import.details.fileRevisions', count( $fileRevisions ) );
		$this->stats->gauge( 'FileImporter.import.details.totalFileSizes', $totalFileSizes );

		$this->logger->info( __METHOD__ . ' ImportOperations built.' );

		return $importOperations;
	}

	private function prepareImportOperations( ImportOperations $importOperations ) {
		if ( !$importOperations->prepare() ) {
			$this->logger->error( __METHOD__ . 'Failed to prepare operations.' );
			throw new ImportException( 'Failed to prepare operations.',
				self::ERROR_OPERATION_PREPARE );
		}

		$this->logger->info( __METHOD__ . ' operations prepared.' );
	}

	private function validateImportOperations( ImportOperations $importOperations ) {
		if ( !$importOperations->validate() ) {
			$this->logger->error( __METHOD__ . 'Failed to validate operations.' );
			throw new ImportException( 'Failed to validate operations.',
				self::ERROR_OPERATION_VALIDATE );
		}

		$this->logger->info( __METHOD__ . ' operations validated.' );
	}

	private function commitImportOperations( ImportOperations $importOperations ) {
		if ( !$importOperations->commit() ) {
			$this->logger->error( __METHOD__ . 'Failed to commit operations.' );
			throw new ImportException( 'Failed to commit operations.',
				self::ERROR_OPERATION_COMMIT );
		}

		$this->logger->info( __METHOD__ . ' operations committed.' );
	}

	/**
	 * @param User $user
	 * @param ImportPlan $importPlan
	 */
	private function validateFileInfoText(
		User $user,
		ImportPlan $importPlan
	) {
		$this->textRevisionValidator->validate(
			$importPlan->getTitle(),
			$user,
			new \WikitextContent( $importPlan->getFileInfoText() ),
			$importPlan->getRequest()->getIntendedSummary(),
			false
		);
	}

	/**
	 * @param ImportPlan $importPlan
	 *
	 * @throws ImportException
	 * @return WikiPage
	 */
	private function getPageFromImportPlan( ImportPlan $importPlan ) {
		/**
		 * T164729 GAID_FOR_UPDATE needed to select for a write
		 */
		$articleIdForUpdate = $importPlan->getTitle()->getArticleID( Title::GAID_FOR_UPDATE );
		$page = $this->wikiPageFactory->newFromId( $articleIdForUpdate );

		if ( $page === null ) {
			throw new ImportException(
				'Failed to create import edit with page id: ' . $articleIdForUpdate,
				self::ERROR_NO_NEW_PAGE );
		}

		return $page;
	}

	/**
	 * @param ImportPlan $importPlan
	 * @param User $user
	 */
	private function createPostImportNullRevision(
		ImportPlan $importPlan,
		User $user
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
	 * @param User $user
	 */
	private function createPostImportEdit(
		ImportPlan $importPlan,
		WikiPage $page,
		User $user
	) {
		$editResult = $page->doEditContent(
			new \WikitextContent( $importPlan->getFileInfoText() ),
			$importPlan->getRequest()->getIntendedSummary(),
			EDIT_UPDATE,
			false,
			$user
		);

		if ( !$editResult->isOK() ) {
			$this->logger->error( __METHOD__ . ' Failed to create user edit.' );
			throw new ImportException(
				'Failed to create user edit', self::ERROR_FAILED_POST_IMPORT_EDIT );
		}
	}

}
