<?php

namespace FileImporter\Services;

use FileImporter\Data\ImportOperations;
use FileImporter\Data\ImportPlan;
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

	public function __construct(
		WikiRevisionFactory $wikiRevisionFactory,
		HttpRequestExecutor $httpRequestExecutor,
		UploadBaseFactory $uploadBaseFactory,
		OldRevisionImporter $oldRevisionImporter,
		UploadRevisionImporter $uploadRevisionImporter,
		LoggerInterface $logger
	) {
		$this->wikiRevisionFactory = $wikiRevisionFactory;
		$this->httpRequestExecutor = $httpRequestExecutor;
		$this->uploadBaseFactory = $uploadBaseFactory;
		$this->oldRevisionImporter = $oldRevisionImporter;
		$this->uploadRevisionImporter = $uploadRevisionImporter;
		$this->logger = $logger;
	}

	/**
	 * @param User $user user to use for the import
	 * @param ImportPlan $importPlan A valid ImportPlan object.
	 *
	 * @return bool success
	 * @throws ImportException
	 */
	public function import(
		User $user,
		ImportPlan $importPlan
	) {
		$importDetails = $importPlan->getDetails();
		$plannedTitle = $importPlan->getTitle();
		$importOperations = new ImportOperations();

		// TODO the type of ImportOperation created should be decided somewhere

		$this->logger->info( __METHOD__ . ' started' );

		/**
		 * Text revisions should be added first. See T147451.
		 * This ensures that the page entry is created and if something fails it can thus be deleted.
		 */
		foreach ( $importDetails->getTextRevisions()->toArray() as $textRevision ) {
			$importOperations->add( new TextRevisionFromTextRevision(
				$plannedTitle,
				$textRevision,
				$this->wikiRevisionFactory,
				$this->oldRevisionImporter,
				$this->logger
			) );
		}

		foreach ( $importDetails->getFileRevisions()->toArray() as $fileRevision ) {
			$importOperations->add( new FileRevisionFromRemoteUrl(
				$plannedTitle,
				$fileRevision,
				$this->httpRequestExecutor,
				$this->wikiRevisionFactory,
				$this->uploadBaseFactory,
				$this->uploadRevisionImporter,
				$this->logger
			) );
		}

		$this->logger->info( __METHOD__ . ' ImportOperations built.' );

		if ( !$importOperations->prepare() ) {
			$this->logger->error( __METHOD__ . 'Failed to prepare operations.' );
			throw new RuntimeException( 'Failed to prepare operations.' );
		}
		$this->logger->info( __METHOD__ . ' operations prepared.' );

		if ( !$importOperations->commit() ) {
			$this->logger->error( __METHOD__ . 'Failed to commit operations.' );
			throw new RuntimeException( 'Failed to commit operations.' );
		}
		$this->logger->info( __METHOD__ . ' operations committed.' );

		// TODO the below should be an ImportOperation
		$page = $this->getPageFromImportPlan( $importPlan );
		$this->createPostImportRevision( $importPlan, $page, $user );
		$this->createPostImportEdit( $importPlan, $page, $user );

		// TODO do we need to call WikiImporter::finishImportPage??
		// TODO factor logic in WikiImporter::finishImportPage out so we can call it

		// TODO If modifications are needed on the text we need to make 1 new revision!

		return true;
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
