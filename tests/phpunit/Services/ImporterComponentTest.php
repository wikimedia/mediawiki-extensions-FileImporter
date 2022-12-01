<?php

namespace FileImporter\Tests\Services;

use FileImporter\Data\FileRevision;
use FileImporter\Data\FileRevisions;
use FileImporter\Data\ImportDetails;
use FileImporter\Data\ImportPlan;
use FileImporter\Data\ImportRequest;
use FileImporter\Data\SourceUrl;
use FileImporter\Data\TextRevision;
use FileImporter\Data\TextRevisions;
use FileImporter\Exceptions\AbuseFilterWarningsException;
use FileImporter\Exceptions\LocalizedImportException;
use FileImporter\Services\FileTextRevisionValidator;
use FileImporter\Services\Http\HttpRequestExecutor;
use FileImporter\Services\Importer;
use FileImporter\Services\NullRevisionCreator;
use FileImporter\Services\UploadBase\UploadBaseFactory;
use FileImporter\Services\UploadBase\ValidatingUploadBase;
use FileImporter\Services\WikiRevisionFactory;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Permissions\Authority;
use MediaWiki\Permissions\RestrictionStore;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Storage\PageUpdateStatus;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityLookup;
use Message;
use MessageLocalizer;
use OldRevisionImporter;
use StatusValue;
use UploadRevisionImporter;
use User;
use Wikimedia\TestingAccessWrapper;
use WikiRevision;

/**
 * @covers \FileImporter\Services\Importer
 *
 * @license GPL-2.0-or-later
 * @author Thiemo Kreuz
 */
class ImporterComponentTest extends \MediaWikiIntegrationTestCase {

	private const URL = 'http://w.invalid';
	private const TITLE = 'FilePageTitle';
	private const PREFIX = 'interwiki-prefix';

	private const COMMENT = '<!--This file was moved here using FileImporter from ' . self::URL .
		"-->\n";
	private const CLEANED_WIKITEXT = 'Auto-cleaned wikitext.';
	private const USER_WIKITEXT = 'User-provided wikitext.';

	private const NULL_EDIT_SUMMARY = 'Imported with FileImporter from ' . self::URL;
	private const USER_SUMMARY = 'User-provided summary';

	/** @var User */
	private $user;

	protected function setUp(): void {
		parent::setUp();

		$this->setMwGlobals( 'wgHooks', [] );
		$this->user = $this->getTestUser()->getUser();
	}

	public function testImportingOneFileRevision() {
		$textRevision = $this->newTextRevision();
		$fileRevision = $this->newFileRevision();
		$wikiRevision = $this->createWikiRevisionMock();

		$message = $this->createMock( Message::class );
		$message->expects( $this->exactly( 2 ) )
			->method( 'inContentLanguage' )
			->willReturn( $message );
		$message->expects( $this->exactly( 2 ) )
			->method( 'plain' )
			->willReturn( '' );

		$messageLocalizer = $this->createMock( MessageLocalizer::class );
		$messageLocalizer->expects( $this->exactly( 2 ) )
			->method( 'msg' )
			->willReturn( $message );

		$minimalRequest = new ImportRequest( self::URL );
		$importPlan = $this->newImportPlan( $minimalRequest, $textRevision, $fileRevision, $messageLocalizer );

		$importer = new Importer(
			$this->createWikiPageFactoryMock( $this->user, self::COMMENT . self::CLEANED_WIKITEXT, null ),
			$this->createWikiRevisionFactoryMock( $textRevision, $fileRevision, $wikiRevision ),
			$this->createNullRevisionCreatorMock( $this->user ),
			$this->createMock( UserIdentityLookup::class ),
			$this->createHttpRequestExecutorMock(),
			$this->createUploadBaseFactoryMock( $this->user, $textRevision ),
			$this->createOldRevisionImporterMock( $wikiRevision ),
			$this->createUploadRevisionImporterMock( $wikiRevision ),
			new FileTextRevisionValidator(),
			$this->getServiceContainer()->getRestrictionStore()
		);

		$importer->import( $this->user, $importPlan );
	}

	public function testImportingOneFileRevisionWithUserProvidedValues() {
		$textRevision = $this->newTextRevision();
		$fileRevision = $this->newFileRevision();
		$wikiRevision = $this->createWikiRevisionMock();
		$messageLocalizer = $this->createMock( MessageLocalizer::class );

		$request = new ImportRequest( self::URL, null, self::USER_WIKITEXT, self::USER_SUMMARY );
		$importPlan = $this->newImportPlan( $request, $textRevision, $fileRevision, $messageLocalizer );

		$importer = new Importer(
			$this->createWikiPageFactoryMock( $this->user, self::USER_WIKITEXT, self::USER_SUMMARY ),
			$this->createWikiRevisionFactoryMock( $textRevision, $fileRevision, $wikiRevision ),
			$this->createNullRevisionCreatorMock( $this->user ),
			$this->createMock( UserIdentityLookup::class ),
			$this->createHttpRequestExecutorMock(),
			$this->createUploadBaseFactoryMock( $this->user, $textRevision ),
			$this->createOldRevisionImporterMock( $wikiRevision ),
			$this->createUploadRevisionImporterMock( $wikiRevision ),
			new FileTextRevisionValidator(),
			$this->getServiceContainer()->getRestrictionStore()
		);

		$importer->import( $this->user, $importPlan );
	}

	public function testValidateImportOperations() {
		$importPlan = $this->createMock( ImportPlan::class );
		$importPlan->expects( $this->exactly( 2 ) )
			->method( 'getValidationWarnings' )
			->willReturn( [ 2, 3 ] );
		$importPlan->expects( $this->once() )
			->method( 'addValidationWarning' )
			->with( 1 );

		$apiMessage1 = \ApiMessage::create( '1', null, [
			'abusefilter' => [
				'id' => 1,
				'actions' => [ 'warn' ]
			]
		] );

		$apiMessage2 = \ApiMessage::create( '2', null, [
			'abusefilter' => [
				'id' => 2,
				'actions' => [ 'warn' ]
			]
		] );

		$status = $this->createMock( StatusValue::class );
		$status->expects( $this->once() )
			->method( 'isGood' )
			->willReturn( false );
		$status->expects( $this->once() )
			->method( 'getErrors' )
			->willReturn( [
				[ 'message' => $apiMessage1 ],
				[ 'message' => $apiMessage2 ]
			] );

		$importer = new Importer(
			$this->createMock( WikiPageFactory::class ),
			$this->createMock( WikiRevisionFactory::class ),
			$this->createMock( NullRevisionCreator::class ),
			$this->createMock( UserIdentityLookup::class ),
			$this->createMock( HttpRequestExecutor::class ),
			$this->createMock( UploadBaseFactory::class ),
			$this->createMock( OldRevisionImporter::class ),
			$this->createMock( UploadRevisionImporter::class ),
			new FileTextRevisionValidator(),
			$this->createMock( RestrictionStore::class )
		);

		/** @var Importer $importer */
		$importer = TestingAccessWrapper::newFromObject( $importer );

		try {
			$importer->validateImportOperations( $status, $importPlan );
			$this->fail( 'Failed asserting that exception of type "ImportValidationException" is thrown.' );
		} catch ( AbuseFilterWarningsException $e ) {
			$this->assertCount( 1, $e->getMessages() );
			$this->assertArrayEquals( [ $apiMessage1 ], $e->getMessages() );
		}
	}

	public function testValidateImportOperationsWithAbuseFilterDisallow() {
		$importPlan = $this->createMock( ImportPlan::class );
		$importPlan->expects( $this->once() )
			->method( 'getValidationWarnings' )
			->willReturn( [] );
		$importPlan->expects( $this->once() )
			->method( 'addValidationWarning' )
			->with( 1 );

		$apiMessage1 = \ApiMessage::create( '1', null, [
			'abusefilter' => [
				'id' => 1,
				'actions' => [ 'warn' ]
			]
		] );

		$apiMessage2 = \ApiMessage::create( '2', null, [
			'abusefilter' => [
				'id' => 2,
				'actions' => [ 'disallow' ]
			]
		] );

		$status = $this->createMock( StatusValue::class );
		$status->expects( $this->once() )
			->method( 'isGood' )
			->willReturn( false );
		$status->expects( $this->once() )
			->method( 'getErrors' )
			->willReturn( [
				[ 'message' => $apiMessage1 ],
				[ 'message' => $apiMessage2 ]
			] );

		$importer = new Importer(
			$this->createMock( WikiPageFactory::class ),
			$this->createMock( WikiRevisionFactory::class ),
			$this->createMock( NullRevisionCreator::class ),
			$this->createMock( UserIdentityLookup::class ),
			$this->createMock( HttpRequestExecutor::class ),
			$this->createMock( UploadBaseFactory::class ),
			$this->createMock( OldRevisionImporter::class ),
			$this->createMock( UploadRevisionImporter::class ),
			new FileTextRevisionValidator(),
			$this->createMock( RestrictionStore::class )
		);

		/** @var Importer $importer */
		$importer = TestingAccessWrapper::newFromObject( $importer );

		$this->expectException( LocalizedImportException::class );
		$importer->validateImportOperations( $status, $importPlan );
	}

	public function testValidateImportOperationsWithStatusParams() {
		/** @var Importer $importer */
		$importer = TestingAccessWrapper::newFromObject( new Importer(
			$this->createMock( WikiPageFactory::class ),
			$this->createMock( WikiRevisionFactory::class ),
			$this->createMock( NullRevisionCreator::class ),
			$this->createMock( UserIdentityLookup::class ),
			$this->createMock( HttpRequestExecutor::class ),
			$this->createMock( UploadBaseFactory::class ),
			$this->createMock( OldRevisionImporter::class ),
			$this->createMock( UploadRevisionImporter::class ),
			new FileTextRevisionValidator(),
			$this->createMock( RestrictionStore::class )
		) );

		$status = StatusValue::newFatal( 'fileimporter-cantimportfileinvalid', 'The reason' );
		$this->expectExceptionMessage( 'The reason' );
		$importer->validateImportOperations( $status, $this->createMock( ImportPlan::class ) );
	}

	private function newImportPlan(
		ImportRequest $request,
		TextRevision $textRevision,
		FileRevision $fileRevision,
		MessageLocalizer $messageLocalizer
	) {
		$details = new ImportDetails(
			new SourceUrl( self::URL ),
			new \TitleValue( NS_FILE, self::TITLE ),
			new TextRevisions( [ $textRevision ] ),
			new FileRevisions( [ $fileRevision ] )
		);
		$config = MediaWikiServices::getInstance()->getMainConfig();

		$plan = new ImportPlan( $request, $details, $config, $messageLocalizer, self::PREFIX );
		$plan->setCleanedLatestRevisionText( self::CLEANED_WIKITEXT );
		return $plan;
	}

	private function newTextRevision() {
		return new TextRevision( [
			'minor' => null,
			'user' => null,
			'timestamp' => '20190101000000',
			'sha1' => null,
			'contentmodel' => null,
			'contentformat' => null,
			'comment' => null,
			'*' => null,
			'title' => null,
			'tags' => [],
		] );
	}

	private function newFileRevision() {
		return new FileRevision( [
			'name' => null,
			'description' => null,
			'user' => null,
			'timestamp' => '20190101000000',
			'sha1' => null,
			'size' => null,
			'thumburl' => null,
			'url' => self::URL,
		] );
	}

	/**
	 * @return WikiRevision
	 */
	private function createWikiRevisionMock(): WikiRevision {
		$revision = $this->createMock( WikiRevision::class );
		$revision->expects( $this->once() )
			->method( 'getContent' )
			->willReturn( new \TextContent( '' ) );
		return $revision;
	}

	private function createWikiRevisionFactoryMock(
		TextRevision $expectedTextRevision,
		FileRevision $expectedFileRevision,
		WikiRevision $returnedWikiRevision
	): WikiRevisionFactory {
		$factory = $this->createMock( WikiRevisionFactory::class );
		$factory->expects( $this->once() )
			->method( 'newFromTextRevision' )
			->with( $expectedTextRevision )
			->willReturn( $returnedWikiRevision );
		$factory->expects( $this->once() )
			->method( 'newFromFileRevision' )
			->with( $expectedFileRevision, $this->stringContains( 'fileimporter_' ) )
			->willReturn( $returnedWikiRevision );
		return $factory;
	}

	/**
	 * @param Authority $expectedUser
	 * @param string $expectedWikitext
	 * @param string|null $expectedSummary
	 *
	 * @return WikiPageFactory
	 */
	private function createWikiPageFactoryMock(
		Authority $expectedUser,
		$expectedWikitext,
		$expectedSummary
	): WikiPageFactory {
		$page = $this->createMock( \WikiPage::class );
		$page->expects( $this->never() )
			->method( 'getTitle' );
		$page->expects( $this->once() )
			->method( 'doUserEditContent' )
			->with(
				new \WikitextContent( $expectedWikitext ),
				$expectedUser,
				$expectedSummary,
				EDIT_UPDATE,
				false,
				[ 'fileimporter' ]
			)
			->willReturn( PageUpdateStatus::newGood() );

		$factory = $this->createMock( WikiPageFactory::class );
		$factory->expects( $this->once() )
			->method( 'newFromID' )
			->with( 0 )
			->willReturn( $page );
		return $factory;
	}

	private function createHttpRequestExecutorMock(): HttpRequestExecutor {
		$request = $this->createMock( \MWHttpRequest::class );

		$executor = $this->createMock( HttpRequestExecutor::class );
		$executor->expects( $this->once() )
			->method( 'executeAndSave' )
			->with( self::URL, $this->stringContains( 'fileimporter_' ) )
			->willReturn( $request );
		return $executor;
	}

	private function createUploadBaseFactoryMock(
		User $expectedUser,
		TextRevision $expectedTextRevision
	): UploadBaseFactory {
		$uploadBase = $this->createMock( ValidatingUploadBase::class );
		$uploadBase->expects( $this->once() )
			->method( 'validateTitle' )
			->willReturn( \UploadBase::OK );
		$uploadBase->expects( $this->once() )
			->method( 'validateFile' )
			->willReturn( \StatusValue::newGood() );
		$uploadBase->expects( $this->once() )
			->method( 'validateUpload' )
			->with( $expectedUser, $expectedTextRevision )
			->willReturn( \StatusValue::newGood() );

		$factory = $this->createMock( UploadBaseFactory::class );
		$factory->expects( $this->once() )
			->method( 'newValidatingUploadBase' )
			->with( $this->isInstanceOf( LinkTarget::class ), '' )
			->willReturn( $uploadBase );
		return $factory;
	}

	private function createUploadRevisionImporterMock(
		WikiRevision $expectedWikiRevision
	): UploadRevisionImporter {
		$importer = $this->createMock( UploadRevisionImporter::class );
		$importer->expects( $this->once() )
			->method( 'import' )
			->with( $expectedWikiRevision )
			->willReturn( \StatusValue::newGood( '' ) );
		return $importer;
	}

	private function createOldRevisionImporterMock(
		WikiRevision $expectedWikiRevision
	): OldRevisionImporter {
		$importer = $this->createMock( OldRevisionImporter::class );
		$importer->expects( $this->once() )
			->method( 'import' )
			->with( $expectedWikiRevision )
			->willReturn( true );
		return $importer;
	}

	private function createNullRevisionCreatorMock( UserIdentity $expectedUser ): NullRevisionCreator {
		$creator = $this->createMock( NullRevisionCreator::class );
		$creator->expects( $this->once() )
			->method( 'createForLinkTarget' )
			->with(
				$this->callback( static function ( LinkTarget $title ) {
					return $title->inNamespace( NS_FILE )
						&& $title->getText() === self::TITLE;
				} ),
				$this->isInstanceOf( FileRevision::class ),
				$expectedUser,
				self::NULL_EDIT_SUMMARY
			)
			->willReturn( $this->createMock( RevisionRecord::class ) );
		return $creator;
	}

}
