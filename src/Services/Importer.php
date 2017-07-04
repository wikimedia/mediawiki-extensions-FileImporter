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

		foreach ( $importDetails->getFileRevisions()->toArray() as $fileRevision ) {
			$importOperations->add( new FileRevisionFromRemoteUrl(
				$plannedTitle,
				$fileRevision,
				$this->httpRequestExecutor,
				$this->wikiRevisionFactory,
				$this->uploadBaseFactory
			) );
		}

		foreach ( $importDetails->getTextRevisions()->toArray() as $textRevision ) {
			$importOperations->add( new TextRevisionFromTextRevision(
				$plannedTitle,
				$textRevision,
				$this->wikiRevisionFactory
			) );
		}

		if ( !$importOperations->prepare() ) {
			$this->logger->error( 'Failed to prepare operations.' );
			throw new RuntimeException( 'Failed to prepare operations.' );
		}

		if ( !$importOperations->commit() ) {
			$this->logger->error( 'Failed to commit operations.' );
			if ( !$importOperations->rollback() ) {
				$this->logger->critical( 'Failed to rollback operations.' );
				throw new RuntimeException( 'Failed to commit and rollback operations.' );
			} else {
				$this->logger->info( 'Successfully rolled back operations' );
				throw new RuntimeException( 'Failed to commit operations, but rolled back successfully!' );
			}
		}

		// TODO the below should be an ImportOperation
		$this->createPostImportNullRevision( $importPlan, $user );

		// TODO do we need to call WikiImporter::finishImportPage??
		// TODO factor logic in WikiImporter::finishImportPage out so we can call it

		// TODO If modifications are needed on the text we need to make 1 new revision!

		return true;
	}

	private function createPostImportNullRevision(
		ImportPlan $importPlan,
		User $user
	) {
		$this->nullRevisionCreator->createForLinkTarget(
			// T164729 GAID_FOR_UPDATE needed to select for a write
			$importPlan->getTitle()->getArticleID( Title::GAID_FOR_UPDATE ),
			$user,
			'Imported from ' . $importPlan->getRequest()->getUrl(), // TODO i18n
			true
		);
	}

}
