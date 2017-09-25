<?php

namespace FileImporter\Services;

use FileImporter\Data\ImportOperations;
use FileImporter\Data\ImportPlan;
use FileImporter\Exceptions\ImportException;
use FileImporter\Operations\FileRevisionFromRemoteUrl;
use FileImporter\Operations\TextRevisionFromTextRevision;
use FileImporter\Services\Http\HttpRequestExecutor;
use FileImporter\Services\UploadBase\UploadBaseFactory;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;
use Title;
use User;

/**
 * Performs an import of a file to the local wiki based on an ImportPlan object for a given User.
 */
class Importer implements LoggerAwareInterface {

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
	 * @param WikiRevisionFactory $wikiRevisionFactory
	 * @param NullRevisionCreator $nullRevisionCreator
	 * @param HttpRequestExecutor $httpRequestExecutor
	 * @param UploadBaseFactory $uploadBaseFactory
	 */
	public function __construct(
		WikiRevisionFactory $wikiRevisionFactory,
		NullRevisionCreator $nullRevisionCreator,
		HttpRequestExecutor $httpRequestExecutor,
		UploadBaseFactory $uploadBaseFactory
	) {
		$this->wikiRevisionFactory = $wikiRevisionFactory;
		$this->nullRevisionCreator = $nullRevisionCreator;
		$this->httpRequestExecutor = $httpRequestExecutor;
		$this->uploadBaseFactory = $uploadBaseFactory;
		$this->logger = new NullLogger();
	}

	public function setLogger( LoggerInterface $logger ) {
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

		/**
		 * Text revisions should be added first. See T147451.
		 * This ensures that the page entry is created and if something fails it can thus be deleted.
		 */
		foreach ( $importDetails->getTextRevisions()->toArray() as $textRevision ) {
			$importOperations->add( new TextRevisionFromTextRevision(
				$plannedTitle,
				$textRevision,
				$this->wikiRevisionFactory
			) );
		}

		foreach ( $importDetails->getFileRevisions()->toArray() as $fileRevision ) {
			$importOperations->add( new FileRevisionFromRemoteUrl(
				$plannedTitle,
				$fileRevision,
				$this->httpRequestExecutor,
				$this->wikiRevisionFactory,
				$this->uploadBaseFactory
			) );
		}

		if ( !$importOperations->prepare() ) {
			$this->logger->error( 'Failed to prepare operations.' );
			throw new RuntimeException( 'Failed to prepare operations.' );
		}

		if ( !$importOperations->commit() ) {
			$this->logger->error( 'Failed to commit operations.' );
			throw new RuntimeException( 'Failed to commit operations.' );
		}

		// TODO the below should be an ImportOperation
		$articleIdForUpdate = $this->getArticleIdForUpdate( $importPlan );
		$this->createPostImportNullRevision( $importPlan, $articleIdForUpdate, $user );
		$this->createPostImportEdit( $importPlan, $articleIdForUpdate, $user );

		// TODO do we need to call WikiImporter::finishImportPage??
		// TODO factor logic in WikiImporter::finishImportPage out so we can call it

		// TODO If modifications are needed on the text we need to make 1 new revision!

		return true;
	}

	/**
	 * T164729 GAID_FOR_UPDATE needed to select for a write
	 *
	 * @param ImportPlan $importPlan
	 *
	 * @return int
	 */
	private function getArticleIdForUpdate( ImportPlan $importPlan ) {
		return $importPlan->getTitle()->getArticleID( Title::GAID_FOR_UPDATE );
	}

	/**
	 * @param ImportPlan $importPlan
	 * @param int $articleIdForUpdate
	 * @param User $user
	 */
	private function createPostImportNullRevision(
		ImportPlan $importPlan,
		$articleIdForUpdate,
		User $user
	) {
		$this->nullRevisionCreator->createForLinkTarget(
			$articleIdForUpdate,
			$user,
			'Imported from ' . $importPlan->getRequest()->getUrl(), // TODO i18n
			true
		);
	}

	/**
	 * @param ImportPlan $importPlan
	 * @param int $articleIdForUpdate
	 * @param User $user
	 */
	private function createPostImportEdit(
		ImportPlan $importPlan,
		$articleIdForUpdate,
		User $user
	) {
		$page = \WikiPage::newFromID( $articleIdForUpdate );
		$editResult = $page->doEditContent(
			new \WikitextContent( $importPlan->getFileInfoText() ),
			// TODO i18n / user provided edit summary
			'User edit on import',
			EDIT_UPDATE,
			false,
			$user
		);

		if ( !$editResult->isOK() ) {
			throw new RuntimeException( 'Failed to create user edit' );
		}
	}

}
