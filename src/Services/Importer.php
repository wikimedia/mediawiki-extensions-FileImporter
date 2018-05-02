<?php

namespace FileImporter\Services;

use Exception;
use FileImporter\Data\ImportOperations;
use FileImporter\Data\ImportPlan;
use FileImporter\Exceptions\ValidationException;
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

	/**
	 * @var WikiRevisionFactory
	 */
	private $wikiRevisionFactory;

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
		WikiRevisionFactory $wikiRevisionFactory,
		HttpRequestExecutor $httpRequestExecutor,
		UploadBaseFactory $uploadBaseFactory,
		OldRevisionImporter $oldRevisionImporter,
		UploadRevisionImporter $uploadRevisionImporter,
		FileTextRevisionValidator $textRevisionValidator,
		StatsdDataFactoryInterface $statsdDataFactory,
		LoggerInterface $logger
	) {
		$this->wikiRevisionFactory = $wikiRevisionFactory;
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
		$importStart = microtime( true );
		$this->logger->info( __METHOD__ . ' started' );

		$importDetails = $importPlan->getDetails();
		$plannedTitle = $importPlan->getTitle();
		$importOperations = new ImportOperations();

		$textRevisions = $importDetails->getTextRevisions()->toArray();
		$fileRevisions = $importDetails->getFileRevisions()->toArray();

		$this->stats->gauge( 'FileImporter.import.details.textRevisions', count( $textRevisions ) );
		$this->stats->gauge( 'FileImporter.import.details.fileRevisions', count( $fileRevisions ) );

		$this->validateFileInfoText(
			$user,
			$importPlan
		);

		// TODO the type of ImportOperation created should be decided somewhere

		/**
		 * Text revisions should be added first. See T147451.
		 * This ensures that the page entry is created and if something fails it can thus be deleted.
		 */
		$operationBuildingStart = microtime( true );
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
		$initialTextRevision = $textRevisions[0];
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
		$this->stats->gauge(
			'FileImporter.import.details.totalFileSizes',
			$totalFileSizes
		);
		$this->stats->timing(
			'FileImporter.import.timing.buildOperations',
			microtime( true ) - $operationBuildingStart
		);

		$this->logger->info( __METHOD__ . ' ImportOperations built.' );

		$operationPrepareStart = microtime( true );
		if ( !$importOperations->prepare() ) {
			$this->logger->error( __METHOD__ . 'Failed to prepare operations.' );
			throw new RuntimeException( 'Failed to prepare operations.' );
		}
		$this->logger->info( __METHOD__ . ' operations prepared.' );
		$this->stats->timing(
			'FileImporter.import.timing.prepareOperations',
			microtime( true ) - $operationPrepareStart
		);

		$operationValidateStart = microtime( true );
		if ( !$importOperations->validate() ) {
			$this->logger->error( __METHOD__ . 'Failed to validate operations.' );
			throw new RuntimeException( 'Failed to validate operations.' );
		}
		$this->logger->info( __METHOD__ . ' operations validated.' );
		$this->stats->timing(
			'FileImporter.import.timing.validateOperations',
			microtime( true ) - $operationValidateStart
		);

		$operationCommitStart = microtime( true );
		if ( !$importOperations->commit() ) {
			$this->logger->error( __METHOD__ . 'Failed to commit operations.' );
			throw new RuntimeException( 'Failed to commit operations.' );
		}
		$this->logger->info( __METHOD__ . ' operations committed.' );
		$this->stats->timing(
			'FileImporter.import.timing.commitOperations',
			microtime( true ) - $operationCommitStart
		);

		// TODO the below should be an ImportOperation
		$miscActionsStart = microtime( true );
		$page = $this->getPageFromImportPlan( $importPlan );
		$this->createPostImportRevision( $importPlan, $page, $user );
		$this->createPostImportEdit( $importPlan, $page, $user );
		$this->stats->timing(
			'FileImporter.import.timing.miscActions',
			microtime( true ) - $miscActionsStart
		);

		// TODO do we need to call WikiImporter::finishImportPage??
		// TODO factor logic in WikiImporter::finishImportPage out so we can call it

		$this->stats->timing(
			'FileImporter.import.timing.wholeImport',
			microtime( true ) - $importStart
		);

		return true;
	}

	/**
	 * @param User $user
	 * @param ImportPlan $importPlan
	 *
	 * @throws ValidationException
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
	 * @throws RuntimeException
	 * @return WikiPage
	 */
	private function getPageFromImportPlan( ImportPlan $importPlan ) {
		/**
		 * T164729 GAID_FOR_UPDATE needed to select for a write
		 * T181391 Pass fromdbmaster as the page has only just been created and in
		 * multi db setups slaves will have lag.
		 */
		$articleIdForUpdate = $importPlan->getTitle()->getArticleID( Title::GAID_FOR_UPDATE );
		$page = WikiPage::newFromID(
			$articleIdForUpdate,
			'fromdbmaster'
		);

		if ( $page === null ) {
			throw new RuntimeException(
				'Failed to create import edit with page id: ' . $articleIdForUpdate
			);
		}

		return $page;
	}

	/**
	 * @param ImportPlan $importPlan
	 * @param WikiPage $page
	 * @param User $user
	 */
	private function createPostImportRevision(
		ImportPlan $importPlan,
		WikiPage $page,
		User $user
	) {
		$config = MediaWikiServices::getInstance()->getMainConfig();

		$editResult = $page->doEditContent(
			new \WikitextContent( $importPlan->getInitialFileInfoText() ),
			wfMsgReplaceArgs(
				$config->get( 'FileImporterCommentForPostImportRevision' ),
				[ $importPlan->getRequest()->getUrl() ]
			),
			EDIT_MINOR,
			false,
			$user
		);

		if ( !$editResult->isOK() ) {
			$this->logger->error( __METHOD__ . ' Failed to create import revision.' );
			throw new RuntimeException( 'Failed to create import revision' );
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
			throw new RuntimeException( 'Failed to create user edit' );
		}
	}

}
